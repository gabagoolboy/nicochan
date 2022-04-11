onready(function postHover() {

    const init_delay = 70;
    const fade_delay = 200;
    const fade_duration = 0;

    const quote_selector = 'div.body a:not([rel="nofollow"]), span.mentioned a';
    let timer;
    let hover_active = false;
    let hover_stack = [];
    let dont_fetch_again = [];

    function bootstrap() {
        unbindHover($('body'));

        $(quote_selector).each(initHover);

        $(window).on('new_post', function (evt, post) {
            unbindHover($(post));
            $(post).find(quote_selector).each(initHover);

            const reply_id = $(post).attr('id').replace(/(^reply_)|(^op_)/, '');
            $(post).find('div.body a:not([rel="nofollow"])').each(function (_, link) {
                let id, $post, $mentioned;
                if (id = $(link).text().match(/^>>(\d+)$/)) id = id[1];
                else return;
                $post = $(`#reply_${id}:not(.post-hover)`);
                if ($post.length == 0) {
                    $post = $(`#op_${id}:not(.post-hover)`);
                    if ($post.length == 0) return;
                }
                $mentioned = $post.find('p.intro span.mentioned');
                if ($mentioned.length == 0)
                    $mentioned = $('<span class="mentioned unimportant"></span>').appendTo($post.find('p.intro'));

                let $link = $mentioned.find(`a.mentioned-${reply_id}`);
                if ($link.length != 0) {
                    $link.off('mouseenter').off('mouseleave').off('mousemove');
                    initHover(null, $link);
                }
            });
        });
    }

    function unbindHover($root) {
        $root.find(quote_selector)
            .off('mouseenter').off('mouseleave').off('mousemove');
    }

    function initHover(_, link) {
        const $link = $(link);
        let $parent;
        let $root;
        let board;
        let depth;
        let parent_id;
        let matches;

        $parent = $link.closest('div.post.reply, div.thread');
        $root = $link.closest('div.post.reply:not([data-hover-depth]), div.thread:not([data-hover-depth])');
        board = $link.closest('[data-board]');
        matches = $link.text().match(/^>>(?:>\/([^\/]+)\/)?(\d+)$/);

        if ($parent.length === 0 || board.length === 0 || !matches)
            return;

        board = board.data('board');
        if (matches[1])
            board = matches[1];
        parent_id = $parent.find('>.intro a.post_no:not([id]), >.post.op >.intro a.post_no:not([id])').text();
        depth = Number.parseInt($parent.attr('data-hover-depth')) || 0;

        $link.data('_id', Number.parseInt(matches[2])).data('_root', $root).data('_parent', $parent)
            .data('_board', board).data('_parent_id', parent_id).data('_depth', depth);

        let initTimeout;
        $link.mouseover(function (mouseover) {
            initTimeout = linkMouseover(mouseover);
        }).mouseout(function (mouseout) {
            linkMouseout(mouseout, initTimeout);
        });
    }

    function linkMouseover(mouseover) {
        mouseover.stopPropagation();
        const $link = $(mouseover.target)
        const id = $link.data('_id');
        const depth = $link.data('_depth');
        let $post;

        return setTimeout(function () {
            if (hover_active && !hover_stack.includes($link[0])) {
                removeHover(depth);
                hover_active = true;
            }

            $(window).trigger('hover-init', [depth + 1]);

            if (hover_active && hover_stack.includes($link[0]))
                return;

            hover_stack.push($link[0]);

            $post = $(`div.post#reply_${id}, div#thread_${id}`);
            if ($post.length > 0)
                appendQuote($link, $post);
            else {
                fetchQuote($link);
            }
        }, init_delay);
    }

    function linkMouseout(mouseout, initTimeout) {
        mouseout.stopPropagation();
        clearTimeout(initTimeout);
        $(window).trigger('hover-exit');
    }

    $(window).on('hover-init', function (evt, depth) {
        hover_active = true;
        clearTimeout(timer);
        timer = setTimeout(() => { removeHover(depth); hover_active = true; }, fade_delay);
    });

    $(window).on('hover-exit', function (evt) {
        clearTimeout(timer);
        timer = setTimeout(() => removeHover(0), fade_delay);
    });

    function removeHover(depth) {
        let $posts_for_deletion = $('.post-hover:not(.fade-out)').filter((_, post) => {
            const a = Number.parseInt($(post).attr('data-hover-depth'));
            const b = Number.parseInt(depth);
            return a > b;
        });
        $posts_for_deletion.addClass('fade-out');
        hover_active = false;
        let n = hover_stack.length - depth;
        for (n; n > 0; n--)
            hover_stack.pop();
        setTimeout(() => $posts_for_deletion.remove(), fade_duration);
    }

    function appendQuote($link, $post) {
        const link_rect = $link[0].getBoundingClientRect();
        const inner_depth = $link.data('_depth') + 1;
        const $new_post = $post.clone();
        $new_post.find('>.reply, >br, a.post_anchor').remove();

        $new_post.find(quote_selector).each(function (_, quote) {
            const matches = $(quote).text().match(/^>>(?:>\/([^\/]+)\/)?(\d+)$/);
            if (matches && matches[2] == $link.data('_parent_id')) {
                let color = '#000';
                const rgb = window.getComputedStyle($post[0]).backgroundColor
                    .match(/rgba?\((\d{1,3}), (\d{1,3}), (\d{1,3})(?:, (.*))?\)/);
                if (rgb) {
                    rgb[4] = rgb[4] ? Number.parseFloat(rgb[4]) : 1;
                    if ((rgb[1]*0.299 + rgb[2]*0.587 + rgb[3]*0.114 + (1 - rgb[4])*255) < 125)
                        color = '#fff';
                }
                $(quote).css('border', `1px dotted ${color}`);
            }
        });

        $new_post.attr('id', `post-hover-${$link.data('_id')}`)
            .attr('data-hover-depth', inner_depth)
            .addClass('post-hover')
            .css('border-style', 'solid')
            .css('box-shadow', '1px 1px 1px #999')
            .css('display', 'block')
            .css('position', 'absolute')
            .css('z-index', 2 * inner_depth)
            .css('animation-duration', `${fade_duration / 1000}s`)
            .css('visibility', 'hidden')
            .addClass('reply').addClass('post')
            .appendTo($link.data('_root'));

        let v = 'top';
        let h = 'left';
        let left = link_rect.right - link_rect.width / 2;
        if (left > window.innerWidth / 2) {
            left -= $new_post.width() + link_rect.width / 2;
            left = left < 0 ? 0 : left;
            h = 'right';
        }
        let top = link_rect.bottom - 5;
        if (top + $new_post.height() > window.innerHeight) {
            top -= ($new_post.height() + 1.5 * link_rect.height);
            v = 'bottom';
        }

        $new_post.detach()
            .css('visibility', 'visible')
            .css('left', left + window.scrollX)
            .css('top', top + window.scrollY)
            .css('transform-origin', `${v} ${h}`)
            .appendTo($link.data('_root'));

        $new_post.mouseover(function (post_mouseover) {
            post_mouseover.stopPropagation();
            $(window).trigger('hover-init', [inner_depth]);
        }).mouseout(function (post_mouseout) {
            post_mouseout.stopPropagation();
            $(window).trigger('hover-exit');
        });

        $new_post.find(quote_selector)
            .css('z-index', 2 * inner_depth + 1)
            .each(initHover);
    }

    function fetchQuote($link) {
        const board = $link.data('_board');
        const id = $link.data('_id');
        let $post;
        const url = $link.attr('href').replace(/#.*$/, '');
        if (dont_fetch_again.includes(url))
            return;
        dont_fetch_again.push(url);

        $.ajax({
            url: url,
            context: document.body,
            success: function (data) {
                const mythreadid = $(data).find('div[id^="thread"]').attr('id').replace('thread_', '');

                if ($(`[data-board="${board}"]#thread_${mythreadid}`).length > 0) {
                    $(data).find('div.post.reply').each(function () {
                        if ($(`[data-board="${board}"] #${$(this).attr('id')}`).length == 0) {
                            $(`[data-board="${board}"]#thread_${mythreadid} .post.reply:first`).before($(this).hide().addClass('hidden'));
                        }
                    })
                }
                else {
                    $(data).find(`div[id^="thread_"]`).hide().attr('data-cached', 'yes').prependTo('form[name="postcontrols"]');
                }

                $post = $(`[data-board="${board}"] div.post#reply_${id}, [data-board="${board}"]div#thread_${id}`);
                if (hover_active && $post.length > 0)
                    appendQuote($link, $post);
            }
        });
    }

    bootstrap();
})();
