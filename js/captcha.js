let tout;

function redoEvents(provider) {
  document.querySelectorAll('.captcha .captcha_text, textarea[id="body"]').forEach(element => {
    element.removeEventListener('focus', onFocusLoadCaptcha);
    element.addEventListener('focus', onFocusLoadCaptcha.bind(null, provider), { once: true });
  });
}

function onFocusLoadCaptcha(provider) {
  actuallyLoadCaptcha(provider);
}

function actuallyLoadCaptcha(provider) {
  document.querySelectorAll('.captcha .captcha_text, textarea[id="body"]').forEach(element => {
    element.removeEventListener('focus', onFocusLoadCaptcha);
  });

  if (tout !== undefined) {
    clearTimeout(tout);
  }

  fetch(`${provider}?mode=get`)
    .then(response => response.json())
    .then(json => {
      updateCaptchaFields(json);
      setTimeout(() => {
        redoEvents(provider);
      }, json.expires_in * 1000);
    })
    .catch(error => console.error('Error fetching captcha data:', error));
}

function updateCaptchaFields(json) {
  const captchaCookie = document.querySelector('.captcha_cookie');
  const captchaHtml = document.querySelector('.captcha .captcha_html');

  if (captchaCookie && captchaHtml) {
    captchaCookie.value = json.cookie;
    captchaHtml.innerHTML = json.html;

    updateQuickReply(json.html, json.cookie);
  }
}

function updateQuickReply(html, cookie) {
  const quickReplyCaptchaHtml = document.querySelector("#quick-reply .captcha .captcha_html");
  const quickReplyCaptchaCookie = document.querySelector("#quick-reply .captcha_cookie");

  if (quickReplyCaptchaHtml && quickReplyCaptchaCookie) {
    quickReplyCaptchaHtml.innerHTML = html;
    quickReplyCaptchaCookie.value = cookie;
  }
}

function loadCaptcha(provider) {
  const captchaTd = document.querySelector('.captcha>td');
  if (captchaTd) {
    captchaTd.innerHTML = `
      <input class='captcha_text' type='text' name='captcha_text' size='25' maxlength='6' autocomplete='off'>
      <input class='captcha_cookie' name='captcha_cookie' type='hidden'>
      <div class='captcha_html'><img id='captcha_click' src='/static/captcha.webp'></div>
    `;
  }

  const quickReplyCaptchaText = document.querySelector("#quick-reply .captcha .captcha_text");
  if (quickReplyCaptchaText) {
    quickReplyCaptchaText.placeholder = _("Verification");
  }

  const captchaHtml = document.querySelector('.captcha .captcha_html');
  if (captchaHtml) {
    captchaHtml.addEventListener('click', () => {
      actuallyLoadCaptcha(provider);
    });
  }

  document.addEventListener('ajax_after_post', () => {
    actuallyLoadCaptcha(provider);
  });

  redoEvents(provider);
  observeQuickReplyElements(provider);
}

function observeQuickReplyElements(provider) {
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1 && node.matches('#quick-reply')) {
          handleQuickReplyElements(provider);
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

function handleQuickReplyElements(provider) {
  redoEvents(provider);

  const quickReplyCaptchaHtml = document.querySelector("#quick-reply .captcha .captcha_html");
  const formCaptchaHtml = document.querySelector("form:not(#quick-reply) .captcha .captcha_html");
  const quickReplyCaptchaCookie = document.querySelector("#quick-reply .captcha_cookie");
  const formCaptchaCookie = document.querySelector("form:not(#quick-reply) .captcha .captcha_cookie");

  if (quickReplyCaptchaHtml && formCaptchaHtml) {
    quickReplyCaptchaHtml.innerHTML = formCaptchaHtml.innerHTML;
  }
  if (quickReplyCaptchaCookie && formCaptchaCookie) {
    quickReplyCaptchaCookie.value = formCaptchaCookie.value;
  }

  if (quickReplyCaptchaHtml) {
    quickReplyCaptchaHtml.addEventListener('click', () => {
      actuallyLoadCaptcha(provider);
      setTimeout(() => {
        const updatedCaptchaHtml = document.querySelector("form:not(#quick-reply) .captcha .captcha_html").innerHTML;
        quickReplyCaptchaHtml.innerHTML = updatedCaptchaHtml;
      }, 100);
    });
  }

   triggerCustomEvent('quick-reply', window);
}

function observeAlertButton() {
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1 && node.classList.contains('alert_button')) {
          node.addEventListener('click', () => {
            actuallyLoadCaptcha(provider_captcha);
          });
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

document.addEventListener('DOMContentLoaded', () => {
  loadCaptcha(provider_captcha);

  observeAlertButton();

});
