(function (win, doc, $) {
    "use strict";

    var item = '<li><label>周{day}时段{section}：</label><input name="course-start[{day}][]" class="time-picker text" type="text" value="{start}" ><span>至</span><input name="course-end[{day}][]" class="time-picker text" type="text" value="{end}" /><a class="ca-close">&times;</a></li>',
        times = [],
        data,
        sectionList = [],
        pop = $('.ca-pop'),
        shown = pop.hasClass('shown'),
        week = pop.find('.ca-pop-week'),
        days = week.find('td'),
        sections = ['a', 'b', 'c'],
        i = 7;

    for (; i; i--) {
        sectionList[i - 1] = $('<ul></ul>', {
            "class": "ca-pop-time",
            id: "ca-pop-time-" + i
        });

        week.after(sectionList[i - 1]);
    }

    function addTime(index, time) {
        var day = times[index],
            count,
            section,
            list = sectionList[index];

        time = time || ['', ''];
        if (!day) {
            day = times[index] = [];
            list.append('<li class="ca-section"><span class="ca-section-title">周' + (+index + 1) +
                '时段</span><a class="ca-section-add" data-day="' + index + '">添加时段</a></li>');

            days.eq(index).addClass('tick');
        }

        count = day.length;

        if (count === 3) {
            list.addClass('full');
            return;
        } else {
            list[count === 2 ? 'addClass' : 'removeClass']('full');
        }

        section = $(item.replace(/\{day\}/g, (+index + 1))
            .replace(/\{section\}/g, sections[count])
            .replace(/\{index\}/g, count)
            .replace('{start}', time[0])
            .replace('{end}', time[1]));

        section.data({
            list: index,
            section: count
        });
        list.append(section);
        day.push(section);
        section.find('input').first().focus();

    }

    function removeTime(index) {
        var list = sectionList[index];

        days.eq(index).removeClass('tick');
        list.empty();
        times[index] = null;
    }

    function popOut() {
        pop.removeClass('shown');
    }

    data = $('.course-arrange').on('click', function (e) {
        var target = e.target,
            $target = $(target),
            targetHeight = $target.outerHeight(),
            position = $target.position();

        if (shown) {
            popOut();
        } else {
            pop.css({
                left: position.left,
                top: position.top + targetHeight + 5
            }).addClass('shown');
        }
    }).data('courses');

    data && $.each(data, function (i, course) {
        course && $.each(course, function (j, section) {
            addTime(i, section);
        });
    });

    pop.on('click', '.ca-section-add', function (e) {
        addTime($(e.target).data('day'));
    });

    pop.on('click', '.ca-pop-confirm', popOut);

    week.on('mouseenter', 'td', function (e) {
        var $target = $(e.target);
        if (!$target.hasClass('tick')) {
            $target.addClass('hover');
        }
    });

    week.on('mouseleave', 'td', function (e) {
        var $target = $(e.target);
        if ($target.hasClass('hover')) {
            $target.removeClass('hover');
        }
    });

    week.on('click', 'td', function (e) {
        var index = $(e.target).index();

        if (times[index]) {
            removeTime(index);
        } else {
            addTime(index);
        }
    });

    pop.on('click', '.ca-close', function (e) {
        var $tar = $(e.target),
            pos = $tar.parent().data(),

            list = times[pos.list],
            index = pos.section;

        list[index].remove();
        list.splice(index, 1);
        $.each(list, function (i, section) {
            var text,
                label = section.find('label');

            section.data('section', i);
            text = label.text().replace(/[a-z]/, sections[i]);
            label.text(text);
        });

        if (!list.length) {
            removeTime(pos.list);
            week.find('td').eq(pos.list).removeClass('tick');
        }
    });

    pop.one('click', '.date-picker', function (e) {
        pop.find('.date-picker').datetimepicker({
            weekStart: 1,
            todayBtn:  1,
            autoclose: 1,
            todayHighlight: 1,
            startView: 2,
            minView: 2,
            forceParse: 0,
            format: 'yyyy-mm-dd'
        });
        e.target.blur();
        e.target.focus();
    });

    pop.on('focus', '.time-picker', function (e) {
        $(e.target).datetimepicker({
            weekStart: 1,
            autoclose: 1,
            startView: 1,
            minView: 0,
            maxView: 1,
            forceParse: 0,
            format: 'hh:ii'
        }).one('changeDate', function () {
            $(this).next().next().focus();
        });
    });
}(window, document, jQuery));