document.addEventListener('DOMContentLoaded', () => {
    hideInput(document);
    hidePostModerations();
});

function hideInput(el) {
    const inputs = el.querySelectorAll('input.delete')
    inputs.forEach(input => {
        input.style.display = 'none';
    });
}

function hidePostModerations() {
    const fields = document.querySelector('#post-moderation-fields');
    if (fields) {
        fields.remove();
    }
}

document.addEventListener('new_post_js', (post) => {
    const newPost = post.detail.detail;
    hideInput(newPost);
});