let isMod;

document.addEventListener("DOMContentLoaded", function() {
    addEventModTools();
    toggleMessageField();
    handleConfirmMessages();
    handleMoveForm();
    doModMenu();

    const rebuildAll = document.getElementById('rebuild_all');
    const boardsAll = document.getElementById('boards_all');
    if (rebuildAll && boardsAll) {
        rebuildAll.addEventListener('change', function() {
            toggleAll('rebuild', this.checked);
        });

        boardsAll.addEventListener('change', function() {
            toggleAll('boards', this.checked);
        });
    }

    ['BanFormID', 'NicenoticeFormID', 'WarningFormID', 'HashbanFormID'].forEach(formId => {
        handlePremade(formId, `${formId.toLowerCase()}-reasons-data`);
    });

    const lastLink = document.querySelector('[id="unimportant last-link"]');

    if (lastLink) {
        const hideModDiv = document.createElement('div');
        hideModDiv.id = 'hide-mod-check';
        hideModDiv.style.textAlign = 'right';
        hideModDiv.style.display = 'inline-block';
        hideModDiv.style.float = 'right';

        const hideCheckbox = document.createElement('input');
        hideCheckbox.type = 'checkbox';
        hideCheckbox.id = 'hide-mod-tools-checkbox';
        hideCheckbox.value = 'hide_mode_tools';

        const hideLabel = document.createElement('label');
        hideLabel.htmlFor = 'hide-mod-tools-checkbox';
        hideLabel.textContent = ' Esconder Ferramentas';

        hideModDiv.appendChild(hideCheckbox);
        hideModDiv.appendChild(hideLabel);

        lastLink.insertAdjacentElement('afterend', hideModDiv);

        hideCheckbox.addEventListener('change', hideModTools);
    }

    isMod = true;

});

document.addEventListener('new_post_js', (event) => {
    addEventModTools(event.detail.detail);
})

function addEventModTools(sel = document) {
    const postDivs = sel.querySelectorAll('span.controls');
    postDivs.forEach(post => {
        addEventToPost(post);
    });
}

function addEventToPost(postElement) {
    if (!postElement) return;
    postElement.addEventListener('click', handleClickEvent);
}

function handleClickEvent(event) {
    const link = event.target.closest('a[data-href]');
    if (link && event.button !== 1) {
        event.preventDefault();
        if (confirm(link.getAttribute('data-confirm'))) {
            window.location.href = link.getAttribute('data-href');
        }
    }
}

function handlePremade(formId, dataElementId) {
    const dataElement = document.getElementById(dataElementId);
    if (dataElement) {
        const reasonsData = JSON.parse(dataElement.textContent || {});
        document.querySelectorAll('.reason-selector').forEach(row => {
            row.addEventListener('click', () => {
                populateForm(document.getElementById(formId), reasonsData[row.dataset.key]);
            });
        });
    }
}

function populateForm(form, data) {
    Object.entries(data).forEach(([key, value]) => {
        const element = form.querySelector(`[name="${key}"]`);
        if (element) {
            element.value = value;
        }
    });
}

function toggleMessageField() {
    const publicMessageCheckbox = document.getElementById('public_message');
    const messageField = document.getElementById('message');

    if (publicMessageCheckbox && messageField) {
        messageField.disabled = !publicMessageCheckbox.checked;
        publicMessageCheckbox.addEventListener('change', () => {
            messageField.disabled = !publicMessageCheckbox.checked;
        });
    }
}

function handleConfirmMessages() {
    const messageLinks = document.querySelectorAll('.link-confirm');
    if (messageLinks) {
        messageLinks.forEach((link) => {
            link.addEventListener('click', function(event) {
                if (!confirm(this.getAttribute('data-confirm-message'))) {
                    event.preventDefault();
                }
            });
        });
    }
}

function handleMoveForm() {
    const form = document.getElementById('move-form');
    const submitButton = document.getElementById('btnSubmit');

    if (form && submitButton) {
        form.addEventListener('submit', () => {
            submitButton.disabled = true;
        });
    }
}

function toggleAll(containerId, checked) {
    const elements = document.getElementById(containerId).querySelectorAll('input[type="checkbox"]');
    elements.forEach(element => element.checked = checked);
}

function hideModTools() {
    const modStuff = document.querySelectorAll('span.mod-ip, #f, .mod-controls, .countrpt');
    const isChecked = document.getElementById('hide-mod-tools-checkbox').checked;
    
    modStuff.forEach(i => {
        i.style.display = isChecked ? 'none' : '';
    });
}

function doModMenu() {
    const globalClickCountsKey = 'globalClickCounts';
    const globalClickCounts = JSON.parse(localStorage.getItem(globalClickCountsKey) || '{}');

    const updateFrequentlyUsed = (post) => {
        const menuLinks = post.querySelectorAll('.menu-content a');
        const frequentlyUsedList = post.querySelector('.frequently-used-list');

        const sortedActions = Object.entries(globalClickCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 4);

        if (frequentlyUsedList) frequentlyUsedList.innerHTML = '';
        sortedActions.forEach(([action, count]) => {
            const link = Array.from(menuLinks).find(link => link.dataset.action === action);
            if (link) {
                const listItem = document.createElement('li');
                const newLink = link.cloneNode(true);
                listItem.appendChild(newLink);
                frequentlyUsedList.appendChild(listItem);
            }
        });
    }

    const updateStyleModMenu = () => {

        if (document.getElementById('mod-menu-css')) return;

		const dummy_reply = Vichan.createElement('div', {
			className: 'post reply',
			parent: document.body,
		});
		const style = window.getComputedStyle(dummy_reply);

        const styleModMenu = `
            .menu-content {
                background-color: ${style.backgroundColor};
                border-style: ${style.borderStyle};
                border-color: ${style.borderColor};
                border-width: ${style.borderWidth};
            }
        `
        dummy_reply.remove();

		Vichan.createElement('style', {
			idName: 'mod-menu-css',
			text: styleModMenu,
			parent: document.head,
		});

    }

    const removeStyleIfAdded = () => {
		const existingStyle = document.getElementById('mod-menu-css');
		if (existingStyle) {
			existingStyle.remove();
            updateStyleModMenu();
		}
    }

    window.addEventListener('stylesheet', removeStyleIfAdded);


    document.querySelectorAll('.post').forEach(post => {
        const hamburgerContainer = post.querySelector('.hamburger-menu-container');
        if (!hamburgerContainer) return;


        const hamburgerIcon = hamburgerContainer.querySelector('.mod-controls');
        const menuContent = hamburgerContainer.querySelector('.menu-content');
        const searchInput = menuContent.querySelector('.search-input');
        const menuLinks = menuContent.querySelectorAll('a');

        updateStyleModMenu();

        menuLinks.forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const action = this.dataset.action;
                const href = this.dataset.href;
                const confirmMessage = this.dataset.confirm;
                if (action === 'restoreshadow' || action === 'permashadowdelete') {
                    window.location.href = this.href;
                }

                if (confirmMessage && !confirm(confirmMessage)) {
                    return;
                }

                globalClickCounts[action] = (globalClickCounts[action] || 0) + 1;
                localStorage.setItem(globalClickCountsKey, JSON.stringify(globalClickCounts));

                document.querySelectorAll('.post').forEach(updateFrequentlyUsed);

                window.location.href = href || this.href;
            });
        });

        updateFrequentlyUsed(post);

        const adjustMenuPosition = () => {
            // i hate css. this is easier
            menuContent.style.left = '';
            menuContent.style.right = '';
            menuContent.style.width = '';

            const menuRect = menuContent.getBoundingClientRect();

            const overflowRight = menuRect.right > window.innerWidth;
            if (overflowRight) {
                menuContent.style.left = 'auto';
                menuContent.style.right = '0';
            }

            const overflowLeft = menuRect.left < 0;
            if (overflowLeft) {
                menuContent.style.left = '0';
                menuContent.style.right = 'auto';
            }

            if (menuRect.width > window.innerWidth) {
                menuContent.style.width = '90%';
            }
        }

        if (hamburgerIcon && menuContent) {
            hamburgerIcon.addEventListener('click', function (e) {
                e.preventDefault()
                hamburgerContainer.classList.toggle('menu-open');
                menuContent.classList.toggle('visible');
                const isOpen = hamburgerContainer.classList.contains('menu-open');
                if (isOpen) {
                    adjustMenuPosition();
                }
            });

            document.addEventListener('click', function (event) {
                if (!hamburgerIcon.contains(event.target)) {
                    menuContent.classList.remove('visible');
                    hamburgerContainer.classList.remove('menu-open');
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const filter = searchInput.value.toLowerCase();
                const menuItems = menuContent.querySelectorAll('ul li');
                menuItems.forEach(function (item) {
                    const text = item.textContent || item.innerText;
                    item.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
                });
            });
        }
    });
}