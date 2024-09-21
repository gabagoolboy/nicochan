(function() {
    // Cache for pages
    let cache = [];
    let loading = false;
    let ukkotimer = null;

    const hiddenboards = JSON.parse(localStorage.getItem('hiddenboards') || "{}");

    const storeBoards = () => {
        localStorage.setItem('hiddenboards', JSON.stringify(hiddenboards));
    };

    const toggleBoardVisibility = function(event) {
        const boardHeader = event.target.closest('h2#board-header');
        const boardElement = boardHeader?.nextElementSibling;

        if (!boardElement || !boardElement.dataset.board) return;

        const board = boardElement.dataset.board;
        hiddenboards[board] = !hiddenboards[board];
        const boardElements = document.querySelectorAll(`[data-board="${board}"]:not([data-cached="yes"])`);

        if (hiddenboards[board]) {
            boardElements.forEach(el => {
                el.style.display = 'none';
                const ukkohideEl = el.previousElementSibling?.querySelector('.ukkohide');
                const hrEl = el.previousElementSibling?.querySelector('hr');
                if (ukkohideEl) ukkohideEl.textContent = _("(show threads from this board)");
                if (hrEl) hrEl.style.display = 'block';
            });
        } else {
            boardElements.forEach(el => {
                el.style.display = 'block';
                const ukkohideEl = el.previousElementSibling?.querySelector('.ukkohide');
                const hrEl = el.previousElementSibling?.querySelector('hr');
                if (ukkohideEl) ukkohideEl.textContent = _("(hide threads from this board)");
                if (hrEl) hrEl.style.display = 'none';
            });
        }

        storeBoards();
        return false;
    };

    const addUkkohide = function(header) {
        const ukkohide = document.createElement('a');
        ukkohide.className = 'unimportant ukkohide';

        const boardElement = header?.nextElementSibling;

        if (!boardElement || !boardElement.dataset.board) return;

        const board = boardElement.dataset.board;
        const hr = document.createElement('hr');

        header.appendChild(ukkohide);
        header.appendChild(hr);

        if (!hiddenboards[board]) {
            ukkohide.textContent = _("(hide threads from this board)");
            hr.style.display = 'none';
        } else {
            ukkohide.textContent = _("(show threads from this board)");
            boardElement.style.display = 'none';
        }

        ukkohide.addEventListener('click', toggleBoardVisibility);
    };

    const showLoadingMessage = (message) => {
        const pages = document.querySelector('.pages');
        if (pages) {
            pages.style.display = 'block';
            pages.innerHTML = message;
        }
    };

    const loadNext = function() {
        const overflow = JSON.parse(document.getElementById('overflow-data')?.textContent || '[]');
        if (overflow.length === 0) {
            showLoadingMessage("No more threads to display");
            return;
        }

        while (window.scrollY + window.innerHeight + 1000 > document.documentElement.scrollHeight && !loading && overflow.length > 0) {
            const nextItem = overflow.shift();
            const page = getModRoot() + nextItem.board + '/' + nextItem.page;
            const thread = document.querySelector(`div#thread_${nextItem.id}[data-board="${nextItem.board}"]`);

            if (thread && thread.getAttribute('data-cached') !== 'yes') {
                continue;
            }

            const boardheader = document.createElement('h2');
            boardheader.id = "board-header";
            boardheader.innerHTML = `<a href="/${nextItem.board}/">/${nextItem.board}/</a>`;

            if (cache.includes(page)) {
                if (thread) {
                    displayThread(thread, boardheader, nextItem.board);
                }
            } else {
                fetchPageData(page, nextItem, boardheader, overflow);
                break;
            }
        }

        clearTimeout(ukkotimer);
        ukkotimer = setTimeout(loadNext, 1000);
    };

    const fetchPageData = (page, nextItem, boardheader, overflow) => {
        loading = true;
        showLoadingMessage("Loading...");

        fetch(page)
            .then(response => response.text())
            .then(data => {
                cache.push(page);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;

                tempDiv.querySelectorAll('div[id*="thread_"]').forEach(threadDiv => {
                    const threadId = threadDiv.id.replace('thread_', '');
                    if (!document.querySelector(`div#thread_${threadId}[data-board="${nextItem.board}"]`)) {
                        const postcontrols = document.querySelector('form[name="postcontrols"]');
                        postcontrols?.insertAdjacentElement('afterbegin', threadDiv);
                        threadDiv.style.display = 'none';
                        threadDiv.setAttribute('data-cached', 'yes');
                        threadDiv.setAttribute('data-board', nextItem.board);
                    }
                });

                const thread = document.querySelector(`div#thread_${nextItem.id}[data-board="${nextItem.board}"][data-cached="yes"]`);

                if (thread) {
                    displayThread(thread, boardheader, nextItem.board);
                }

                loading = false;
                const pages = document.querySelector('.pages');
                if (pages) {
                    pages.style.display = 'none';
                    pages.innerHTML = '';
                }
            });
    };

    const displayThread = (thread, boardheader, board) => {
        const lastThread = document.querySelector('div[id*="thread_"]:last-of-type');

        if (lastThread) {
            lastThread.insertAdjacentElement('afterend', thread);
        } else {
            document.querySelector('form[name="postcontrols"]')?.insertAdjacentElement('afterbegin', thread);
        }

        thread.style.display = 'block';
        thread.setAttribute('data-board', board);
        thread.setAttribute('data-cached', 'no');
        thread.before(boardheader);
        addUkkohide(boardheader);
        triggerCustomEvent('new_post_js', document, { detail: thread });
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('h2#board-header').forEach(header => addUkkohide(header));
        document.querySelector('.pages')?.style.setProperty('display', 'none');
        window.addEventListener('scroll', loadNext);
        ukkotimer = setTimeout(loadNext, 1000);
    });
})();
