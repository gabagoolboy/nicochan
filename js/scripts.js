function addListenersToElements(elements, callback) {
    elements.forEach(element => {
        element.addEventListener('click', function(event) {
            event.preventDefault();

            const cite = event.target.getAttribute('data-cite');

            if (callback(cite, event)) {
                window.location.href = event.target.href;
            }
        });
    });
}

function handleNewElement(newElement, selector, callback) {
    const elements = newElement.querySelectorAll(selector);
    if (elements.length > 0) {
        addListenersToElements(elements, callback);
    }
}

function addFormListener(formId, callback) {
    const form = document.getElementById(formId);

    if (form) {
        form.addEventListener('submit', function(event) {
            if (!callback(form)) {
                event.preventDefault();
            }
        });
    }
}

document.addEventListener("DOMContentLoaded", function() {
    addListenersToElements(document.querySelectorAll('.highlight-link'), highlightReply);
    addListenersToElements(document.querySelectorAll('.cite-link'), citeReply);

    addFormListener('post-form', dopost);
    addFormListener('report-form', doreport);
});

document.addEventListener('new_post_js', (event) => {
    const newPost = event.detail.detail;

    handleNewElement(newPost, '.highlight-link', highlightReply);
    handleNewElement(newPost, '.cite-link', citeReply);
});
