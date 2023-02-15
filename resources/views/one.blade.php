<head>
    <style>
        * {
            box-sizing: border-box;
        }

        table,
        th,
        td {
            border: 1px solid;
            padding: 5px;
        }

        .column {
            float: left;
            width: 50%;
            padding: 10px;
        }

        /* Clear floats after the columns */
        .row:after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>

<body>
    <div class="row">
        <div class="column">
            <h1>Appointments already made</h1>
            @if ($dateDay)
            Day = {{ $dateDay }} | <a href="/one/list">All (days)</a>
            @else
            <h3>Filter by date, clicking on a date <small>(from the 4th column (Date))</small></h3>
            @endif
            <table>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <?php if ($dateDay) echo '';
                    else echo '<th>Date</th>' ?>
                    <th>Hour<br>begin</th>
                    <th>Hour<br>end</th>
                </tr>
                @foreach ($ap as $val)
                <tr>
                    <td>{{ $val->id }}</td>
                    <td>{{ $val->person_name }}</td>
                    <td>{{ $val->person_email }}</td>
                    <?php if ($dateDay) echo '';
                    else echo "<td><a href='/one/list/$val->date'>$val->date</td>"; ?>
                    <td>{{ Str::limit($val->hour_begin, 5, '') }}</td>
                    <td>{{ Str::limit($val->hour_end, 5, '')  }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="column">
            <h1>Time slots available</h1>
            <table>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Hour begin</th>
                    <th>Hour end</th>
                </tr>
                @foreach ($av as $val)
                <tr>
                    <td>{{ $val->id }}</td>
                    <td>{{ $val->date }}</td>
                    <td>{{ Str::limit($val->hour_begin, 5, '') }}</td>
                    <td>{{ Str::limit($val->hour_end, 5, '')  }}</td>
                </tr>
                @endforeach
            </table>

        </div>
    </div>
</body>