<?php

namespace App\Http\Controllers;

//use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
//use Illuminate\Foundation\Bus\DispatchesJobs;
//use Illuminate\Foundation\Validation\ValidatesRequests;

use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB; //(N) for read data from DB
use Illuminate\Support\Carbon;

class OneController extends BaseController
{
    private $_tbAppointments = 'appointments';
    private $_tbAvailable = 'available_time_slots';

    #region todo: will be in session variables and will be read from DB
    private $_wd1 = ['Mo', 'Tu']; //working_days			Mo,Tu,We,Th,Fr (the first 2 letter from the weekdays, separated by comma) + Sa,Su
    private $_wh2 = [['09:00', '13:00'], ['15:30', '21:00']]; //working_hours			9:00-13:00,15:30-21:00
    private $_ad3 = 60; //appointment_duration	60 (in minutes)
    private $_mp4 = 30; //minimum_pause			30 (in minutes, SHOULD be less (or equal) to the working daily interval breaks)

    private $_cpDate; // = '2023-03-06';
    #endregion


    private function _getFrmDB_appointmentsAlreadyMade(?string $dateDay) // ?string for string or null
    {
        $result = DB::table($this->_tbAppointments)
            ->orderBy('date', 'desc')->orderBy('hour_begin', 'desc')
            ->when($dateDay, function ($query) use ($dateDay) {
                if (null != $dateDay)
                    return $query->where('date', $dateDay);
            })->get();

        return $result;
    }

    private function _getFromDB_availableTimeSlots(?string $dateDay)
    {
        $result = DB::table($this->_tbAvailable)
            ->orderBy('date', 'desc')->orderBy('hour_begin', 'desc')
            ->when($dateDay, function ($query) use ($dateDay) {
                if (null != $dateDay)
                    return $query->where('date', $dateDay);
            })->get();

        return $result;
    }

    public function list($dateDay = null)
    {
        return view('one', [
            'ap' => $this->_getFrmDB_appointmentsAlreadyMade($dateDay),
            'av' => $this->_getFromDB_availableTimeSlots($dateDay),
            'dateDay' => $dateDay,
        ]);
    }

    public function book(string $dateDay, string $hourBegin)
    {
        $this->_cpDate = $dateDay;

        $r = rand(0, 10000);
        $p = (object)[ //$p = parameters for appointment
            'person_name' => 'Name_' . $r,
            'person_email' => "email_$r@test.ro",
            'date' => $this->_cpDate,
            'hour_begin' => $hourBegin,
        ];
        $p->hour_end = (Carbon::parse($p->hour_begin)->addMinutes($this->_ad3))->format('H:i'); //'21:00', //'09:00'+$this->_ad3, seconds default is (added) ':00'

        try {
            DB::beginTransaction();
            #region update or split the available_time slot record
            $av = DB::table($this->_tbAvailable)->where('date', $p->date)->orderBy('hour_begin')->get();
            if ($av->isEmpty()) { //(N) empty available time slot ... is NOT yet inserted any timeslot for the given day
                foreach ($this->_wh2 as $interval_slot) {
                    DB::table($this->_tbAvailable)->insert([
                        'date' => $this->_cpDate,
                        'hour_begin' => $interval_slot[0],
                        'hour_end' => $interval_slot[1],
                    ]);
                }
            }

            $av = $this->_getFromDB_availableTimeSlots($this->_cpDate);
            $foundSlotForValidAppointment = false;
            foreach ($av as $slot) {
                if ($this->_updateOrSplitOrDelete_timeSlot($p->hour_begin, [$slot->hour_begin, $slot->hour_end], $this->_cpDate)) {
                    $foundSlotForValidAppointment = true;

                    #region insert intro tbAppointments
                    DB::table($this->_tbAppointments)
                        ->insert((array)$p);
                    #endregion

                    break; // is NOT neccessary to test/search for another interval
                }
            }
            if (!$foundSlotForValidAppointment) {
                echo "<br>(N) Invalid timeslot booking.";
                DB::rollBack();
            } else {
                DB::commit();
                echo "<br>(N) Timeslot was succesfully booked.";
            }
            #endregion

        } catch (Exception $e) {
            DB::rollBack();
            dd($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function _updateOrSplitOrDelete_timeSlot(string $try_to_book_from_hour_beginning, array $available_slot, string $dateForSplit)
    {
        //eg: $try_to_book_from_hour_beginning='09:00'
        //    $available_slot=['09:00', '21:00'] // [begin,end]
        $bookHourBegin = Carbon::parse($try_to_book_from_hour_beginning); //carbonParseTime
        $slotHour = [Carbon::parse($available_slot[0]), Carbon::parse($available_slot[1])];

        if ($slotHour[1]->diffInMinutes($slotHour[0]) < $this->_ad3) { //eg. [20:45, 21:00] // constraint 1
            return false; // impossible case (because the splitted interval should NOT be splitted in this way !), but for security/safety
        }

        if ($bookHourBegin < $slotHour[0]) { //eg. 09:00 < 09:01 // constraint 2
            return false;
        }
        if ((clone ($bookHourBegin))->addMinutes($this->_ad3) > $slotHour[1]) { //eg. 10:00 (from 09:00 + 60 minutes) > 09:59// constraint 3
            return false;
        }

        #region 3. (!! (region 3 is before region 1 and region 2, because of the "short-circuited" scenarios)) check if timeSlot can/will be deleted
        #region delete 1 (delete because the $slotHour_begin)
        //eg. '09:00', ['09:00', '10:00'] => delete 1.1
        //eg. '09:00', ['09:00', '10:30'] => delete 1.2
        //eg. '09:00', ['09:00', '11:00'] => delete 1.3
        //eg. '09:00', ['09:00', '11:29'] => delete 1.4
        //eg. '09:00', ['09:00', '11:30'] => update to [10:30, 11:30] resolved at update 1.1 (see bellow)
        if ($slotHour[1]->diffInMinutes($slotHour[0]) < (2 * $this->_ad3 + $this->_mp4)) {
            DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->delete();
            return true;
        }
        #endregion delete 1

        #region delete 2 (delete the $slotHour_end)
        //eg. '20:00', ['20:00', '21:00'] => see (description for) delete 1.1 !!! (alias delete 2.1)
        //eg. '19:30', ['19:30', '21:00'] => delete 2.2 // included in delete 1
        //eg. '19:00', ['19:00', '21:00'] => delete 2.3 // included in delete 1
        //eg. '18:31', ['18:31', '21:00'] => delete 2.4 // included in delete 1

        //eg. '18:31', ['18:00', '21:00'] => delete 2.4.2 !
        //eg. '18:31', ['17:32', '21:00'] => delete 2.4.3 !!
        //eg. '18:31', ['17:02', '21:00'] => update [17:02, 18:31] - BUG_1 - UNresolved !
        // we have 3 intervals: before appointment, appointment, after appointment ... the conditions is for 2 (before appointment, after appointment)
        // (condition 1) before appointment is resolved at delete 1 ???

        // (condition 2) after appointment (difference between $slotHour[1] and ($bookHourBegin + _ad3) combined with condition 1 (difference between $bookHourBegin and $slotHour[0]!!!
        if (($slotHour[1]->diffInMinutes(((clone ($bookHourBegin))->addMinutes($this->_ad3))) < $this->_ad3) && ($bookHourBegin->diffInMinutes($slotHour[0]) < $this->_ad3)) { //condition 2 && condition 1
            DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->delete();
            return true;
        }

        //eg. '18:31', ['17:01', '21:00'] => update to ... [17:01, 18:31] ??? where to resolve
        //eg. '18:30', ['18:30', '21:00'] => update to [20:00, 21:00]... ??? where to resolve
        //eg. '18:30', ['17:15', '21:00'] => update to [20:00, 21:00]... ??? where to resolve
        //eg. '18:30', ['17:00', '21:00'] => split to [17:00, 18:30] + [20:00, 21:00]... ??? where to resolve
        #endregion delete 1

        #endregion 3. (!!) check if timeSlot can/will be deleted

        #region 1. check if timeSlot can/will be updated
        #region update 1 (modify the $slotHour_begin)
        //eg. '09:00', ['09:00', '21:00'] => updated to [10:30, 21:00] //update 1.1
        //eg. '09:30', ['09:00', '21:00'] => updated to [11:00, 21:00] //update 1.2
        //eg. '10:00', ['09:00', '21:00'] => updated to [11:30, 21:00] //update 1.3
        //eg. '10:29', ['09:00', '21:00'] => updated to [11:59, 21:00] //update 1.4
        if (((clone ($bookHourBegin))->subMinutes($this->_ad3 + $this->_mp4) < $slotHour[0]) &&
            (1 == 1) //delete 4.3 to exclude to be updtated here
        ) {
            DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->update(['hour_begin' => $bookHourBegin->addMinutes($this->_ad3 + $this->_mp4)->format('H:i')]);
            return true;
        } else {
            if (((clone ($bookHourBegin))->subMinutes($this->_ad3 + $this->_mp4) >= $slotHour[0])
                &&
                (!((clone ($slotHour[1]))->subMinutes(2 * $this->_ad3 + $this->_mp4) < $bookHourBegin))
            ) // && condition from update 2
            { //eg. '12:15', ['09:00', '21:00'] => split to [9:00, 11:45] + [13:45, 21:00] //split 1.2 !!!
                //because of the "logical restrictions/reason" split 1.2 is BEFORE split 1.1 !!! !!!
                DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->update(['hour_end' => (clone ($bookHourBegin))->subMinutes($this->_mp4)->format('H:i')]); //with clone, because bellow (at ->insert) I have to use ->addMinutes()
                DB::table($this->_tbAvailable)->insert([
                    'date' => $dateForSplit,
                    'hour_begin' => $bookHourBegin->addMinutes($this->_ad3 + $this->_mp4)->format('H:i'),
                    'hour_end' => $slotHour[1],
                ]);
                return true;
            } //else if (1 == 1) { //eg. '10:30', ['09:00', '21:00'] => split to [9:00, 10:00] + [12:00, 21:00] //split 1.1 !!!
            //is included in split 1.2 !!!
            //}
        }
        #endregion update 1

        #region update 2 (modify the $slotHour_end)
        //eg. '20:00', ['09:00', '21:00'] => updated to [09:00, 19:30] //update 2.1
        //eg. '19:31', ['09:00', '21:00'] => updated to [09:00, 19:01] //update 2.2
        //eg. '19:01', ['09:00', '21:00'] => updated to [09:00, 18:31] //update 2.3
        //eg. '19:00', ['09:00', '21:00'] => updated to [09:00, 18:30] //update 2.4
        //eg. '18:31', ['09:00', '21:00'] => updated to [09:00, 18:01] //update 2.5
        if ((clone ($slotHour[1]))->subMinutes(2 * $this->_ad3 + $this->_mp4) < $bookHourBegin) {
            DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->update(['hour_end' => $bookHourBegin->subMinutes($this->_mp4)->format('H:i')]);
            return true;
        } else {
            //eg. '18:30', ['09:00', '21:00'] => split to [9:00, 18:00] + [20:00, 21:00] //split 2.1 !!!
            //eg. '18:00', ['09:00', '21:00'] => split to [9:00, 17:30] + [19:30, 21:00] //split 2.2 !!!
            if ((clone ($slotHour[1]))->subMinutes(2 * $this->_ad3 + $this->_mp4) >= $bookHourBegin) {
                DB::table($this->_tbAvailable)->where(['date' => $dateForSplit, 'hour_begin' => $available_slot[0]])->update(['hour_end' => (clone ($bookHourBegin))->subMinutes($this->_mp4)->format('H:i')]); //with clone, because bellow (at ->insert) I have to use ->addMinutes()
                DB::table($this->_tbAvailable)->insert([
                    'date' => $dateForSplit,
                    'hour_begin' => $bookHourBegin->addMinutes($this->_ad3 + $this->_mp4)->format('H:i'),
                    'hour_end' => $slotHour[1],
                ]);
                return true;
            }
        }
        #endregion update 2
        #endregion 1. check if timeSlot can/will be updated

        #region 2. check if timeSlot can/will be splitted
        //see (description for) split 1.1 from update 1
        //see (description for) split 1.2 from update 1

        //see (description for) split 2.1 from update 2
        //see (description for) split 2.2 from update 2
        #endregion 2. check if timeSlot can/will be splitted


        return true;


        //??? if it will NOT be updated, then will try to splitTheTimeSlot (if can not be splited, then return false (this appointment can NOT be booked (because of the constraint)))
        //eg: $try_to_book_from_hour_beginning='19:00'
        //    $available_slot=['09:00', '12:00']
        return false;
    }
}
