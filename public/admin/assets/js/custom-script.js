// Toast message
window.addEventListener('show-toast', event => {
    const type = event.detail.type;
    const message = event.detail.message;

    const icons = {
        success: `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" stroke="green" fill="#e6f4ea"/>
                <path d="M9 12l2 2 4-4" stroke="green"/>
            </svg>
        `,
        info: `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#0d6efd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" stroke="#0d6efd" fill="#e7f1ff"/>
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
            </svg>
        `,
        warning: `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#ffc107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 22 20 2 20" fill="#fff8e1" stroke="#ffc107"/>
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
        `,
        danger: `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" fill="#fde8e8" stroke="#dc3545"/>
                <line x1="15" y1="9" x2="9" y2="15" />
                <line x1="9" y1="9" x2="15" y2="15" />
            </svg>
        `
    };

    const container = document.getElementById('toast-container');
    // Remove any existing toast immediately
    const existingToast = container.querySelector('.toast-custom');
    if (existingToast) {
        existingToast.classList.remove('toast-slide-in');
        existingToast.classList.add('toast-slide-out');
        existingToast.addEventListener('animationend', () => existingToast.remove(), { once: true });
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast-slide-in toast-custom toast-${type}`;
    toastEl.role = 'alert';
    toastEl.ariaLive = 'assertive';
    toastEl.ariaAtomic = 'true';
    toastEl.style.opacity = '0'; // start hidden for animation
    toastEl.innerHTML = `
        <i>${icons[type] || ''}</i>
        <div class="flex-grow-1">${message}</div>
        <button type="button" class="btn-close ms-2" aria-label="Close"></button>
    `;

    container.prepend(toastEl);

    requestAnimationFrame(() => {
        toastEl.style.opacity = '1';
        const toast = new bootstrap.Toast(toastEl, { autohide: false });
        toast.show();

        toastEl.addEventListener('animationend', () => {
            setTimeout(() => {
                toastEl.classList.remove('toast-slide-in');
                toastEl.classList.add('toast-slide-out');
                toastEl.addEventListener('animationend', () => toastEl.remove(), { once: true });
            }, 5000);
        }, { once: true });
    });

    const closeBtn = toastEl.querySelector('.btn-close');
    closeBtn.addEventListener('click', () => {
        toastEl.classList.remove('toast-slide-in');
        toastEl.classList.add('toast-slide-out');
        toastEl.addEventListener('animationend', () => toastEl.remove(), { once: true });
    });
});
// /Toast  //
