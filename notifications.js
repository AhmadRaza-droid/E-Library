// Action feedback stays in the page and never blocks reading or form submission.
(function () {
    'use strict';
    const container = document.getElementById('site-notifications');
    if (!container) return;

    container.addEventListener('click', function (event) {
        const button = event.target.closest('.notice-dismiss');
        if (button) button.closest('.site-notice').remove();
    });

    window.showNotification = function (message, type) {
        const notice = document.createElement('div');
        notice.className = 'site-notice';
        if (type === 'error') notice.classList.add('site-notice-error');
        if (type === 'success') notice.classList.add('site-notice-success');
        notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const text = document.createElement('p');
        text.textContent = String(message);
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'notice-dismiss';
        dismiss.setAttribute('aria-label', 'Dismiss notification');
        dismiss.textContent = '×';
        notice.append(text, dismiss);
        container.appendChild(notice);
    };
}());
