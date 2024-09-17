/*
 * show-own-posts.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/show-op.js
 *
 * Adds "(You)" to a name field when the post is yours. Update references as well.
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/ajax.js';
 *   $config['additional_javascript'][] = 'js/show-own-posts.js';
 *
 */

(function () {
  const getPosts = () => JSON.parse(localStorage.getItem('own_posts') || '{}');
  const setPosts = (posts) => localStorage.setItem('own_posts', JSON.stringify(posts));
  const getBoard = () => document.querySelector('input[name="board"]')?.value;

  const updateReferenceMarkers = (postId, action, rootElement = document) => {
    const posts = getPosts();
    const board = currentBoard;

    rootElement.querySelectorAll(`div.body .highlight-link[data-cite="${postId}"]`).forEach(link => {
      const youMarker = link.nextElementSibling?.matches('small.own_post');
      if (action === 'add' && posts[board]?.includes(postId) && !youMarker) {
        link.insertAdjacentHTML('afterend', ` <small class="own_post">${_('(You)')}</small>`);
      } else if (action === 'remove' && youMarker) {
        link.nextElementSibling.remove();
      }
    });
  };

  const modifyPost = (postId, action) => {
    const posts = getPosts();
    const board = currentBoard;
    const postList = posts[board] || [];

    if (action === 'add' && !postList.includes(postId)) {
      postList.push(postId);
    } else if (action === 'remove') {
      const index = postList.indexOf(postId);
      if (index > -1) postList.splice(index, 1);
    }

    if (postList.length) {
      posts[board] = postList;
    } else {
      delete posts[board];
    }

    setPosts(posts);

    const postElement = document.getElementById(`reply_${postId}`) || document.getElementById(`op_${postId}`);
    if (postElement) {
      if (action === 'add') {
        postElement.classList.add('you');
        const nameElement = postElement.querySelector('span.name');
        addYouSmall(nameElement);
      } else {
        postElement.classList.remove('you');
        const ownPostMarker = postElement.querySelector('.own_post');
        if (ownPostMarker) ownPostMarker.remove();
      }
    }

    updateReferenceMarkers(postId, action);
    updateReferencesInsidePost(postElement);
  };

  const updateReferencesInsidePost = (postElement) => {
    const posts = getPosts();
    const board = currentBoard;

    postElement.querySelectorAll('div.body .highlight-link').forEach(link => {
      const citedPostId = link.dataset.cite;
      if (posts[board]?.includes(citedPostId)) {
        const youMarker = link.nextElementSibling?.matches('small.own_post');
        if (!youMarker) {
          link.insertAdjacentHTML('afterend', ` <small class="own_post">${_('(You)')}</small>`);
        }
      }
    });
  };

  const updateAfterAdded = (postElement) => {
    const postId = postElement.id.split('_')[1];
    updateReferenceMarkers(postId, 'add', postElement);
    updateOwnPost(postElement);
    updateReferencesInsidePost(postElement);
  };

  const addYouSmall = (nameElement) => {
    if (nameElement && !nameElement.querySelector('.own_post')) {
      nameElement.insertAdjacentHTML('beforeend', ` <span class="own_post">${_('(You)')}</span>`);
    }
  };

  const updateOwnPost = (postElement) => {
    if (postElement.classList.contains('you')) return;

    const threadElement = postElement.closest('[id^="thread_"]') || postElement;
    const board = threadElement.getAttribute('data-board');
    const posts = getPosts();
    const postId = postElement.id.split('_')[1];

    if (posts[board]?.includes(postId)) {
      postElement.classList.add('you');
      const nameElement = postElement.querySelector('span.name');
      addYouSmall(nameElement);
      updateReferenceMarkers(postId, 'add');
    }
  };

  const updateAllPosts = () => {
    document.querySelectorAll('div.post.op, div.post.reply').forEach(updateOwnPost);
  };

  let currentBoard = null;

  document.addEventListener('DOMContentLoaded', () => {
    currentBoard = getBoard();
    updateAllPosts();
  });

  document.addEventListener('ajax_after_post', (event) => {
    const postId = event.detail.detail.id;
    modifyPost(postId, 'add');
  });

  document.addEventListener('new_post_js', (event) => {
    const post = event.detail.detail;
    updateAfterAdded(post);
  });

  document.addEventListener('menu_ready_js', () => {
    const Menu = window.Menu;
    Menu.add_item("add_you_menu", _("Add (You)"));
    Menu.add_item("remove_you_menu", _("Remove (You)"));

    Menu.onclick((e, menuItem) => {
      const ele = e.target.closest('div.post');
      const postId = ele.querySelector('a.post_no').dataset.cite;
      const addMenu = menuItem.querySelector('#add_you_menu');
      const removeMenu = menuItem.querySelector('#remove_you_menu');

      addMenu.classList.toggle('hidden', ele.classList.contains('you'));
      removeMenu.classList.toggle('hidden', !ele.classList.contains('you'));

      addMenu.onclick = () => {
        modifyPost(postId, 'add');
        updateAfterAdded(ele);
      };

      removeMenu.onclick = () => {
        modifyPost(postId, 'remove');
      };
    });
  });
})();
