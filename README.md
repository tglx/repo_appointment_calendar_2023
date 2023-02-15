# repo_appointment_calendar_2023
## Task:
All the ""MUST"", ""MAY"" and ""SHOULD"" words, with their negative forms, follow the standard defined in RFC2119.

# Appointment calendar: develop a small web application that lets book a consulting appointment.
- In a day may be various appointments, each one lasts 1 hour and is set 30 minutes apart from the others. The consultants are available from Monday to Friday, 9:00-13:00, 15:30-21:00.
- You MUST develop the front-office and the back-office;
- You MUST use PHP (>= ) language and at least one AJAX call;
- You MUST use any RDBMS (SQLite, MySQL, PostgreSQL);
- You MUST use Laravel;
- You MAY provide UML diagrams;
- You SHOULD NOT focus on front-end/UI.


Tehnical details:
DB ... exists 2 tables, see /documentation/DB/var_dump.sql

Version history:
see /documentation/history.txt

Bug list:
see /documentation/bugs.txt

Settings/parameters
The settings are hard-coded in OneController.php
    (NOT implemented (yet)) private $_wd1 = ['Mo', 'Tu']; //working_days			Mo,Tu,We,Th,Fr (the first 2 letter from the weekdays, separated by comma) + Sa,Su
    (NOT implemented (yet) multiple intervals) private $_wh2 = [['09:00', '21:00']]; //['9:00-13:00', '15:30-21:00']; //working_hours			9:00-13:00,15:30-21:00
(NOT tested (yet) with other values) private $_ad3 = 60; //appointment_duration	60 (in minutes)
(NOT tested (yet) with other values) private $_mp4 = 30; //minimum_pause			30 (in minutes, SHOULD be less (or equal) to the working daily interval breaks)
