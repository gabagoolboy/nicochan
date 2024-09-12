let isMod;

document.addEventListener("DOMContentLoaded", function() {
    addEventModTools();
    toggleMessageField();
    handleConfirmMessages();
    handleMoveForm();

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
    const ipInfo = document.querySelectorAll('span.mod-ip');
    const controls = document.querySelectorAll('span.controls')
    const rpt = document.querySelector('.countrpt');

    const isChecked = document.getElementById('hide-mod-tools-checkbox').checked;
    
    ipInfo.forEach(i => {
        i.style.display = isChecked ? 'none' : '';
    })

    controls.forEach(i => {
        i.style.display = isChecked ? 'none' : '';
    })

    if (rpt) {
        rpt.style.display = isChecked ? 'none' : '';
    }
}