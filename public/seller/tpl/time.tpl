<div class="timepicker fade" h-com="time-panel">
    <div class="timepicker-menu" h-com="toggle-open" data-toggle='{"target":".toggle"}'>
        <a class="timepicker-prev-month">{{text.prev.month}}</a>
        <div class="timepicker-year toggle">
            <a class="timepicker-year-field title">{{year}}</a>
            <ul class="timepicker-year-list" h-com="scroll">
                {{for rang.0 rang.1}}
                <li class="title timepicker-year-picker" data-value="{{$index}}">{{$index}}</li>
                {{/for}}
            </ul>
        </div>
        <div class="timepicker-month toggle">
            <a class="timepicker-month-field title" >{{text.months[month]}}</a>
            <ul class="timepicker-month-list" h-com="scroll">
                {{loop 12}}
                <li class="title timepicker-month-picker" data-value="{{$index}}">{{text.months[$index]}}</li>
                {{/loop}}
            </ul>
        </div>
        <a class="timepicker-next-month">{{text.next.month}}</a>
    </div>
    <div class="timepicker-content">
        {{each text.shortDays}}
            <div class="timepicker-days-title">{{$value}}</div>
        {{/each}}
        <div class="timepicker-pick"></div>
    </div>
    <div class="timepicker-footer">
        <a class="timepicker-today">{{text.today}}</a>
        <a class="timepicker-confirm">{{text.confirm}}</a>
    </div>
</div>