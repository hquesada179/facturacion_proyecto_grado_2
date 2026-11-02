/**
 * The "Verifica tu correo" OTP form: six single-digit boxes that
 * collapse into one hidden `code` input before submit — the server
 * only ever sees one POST with the full 6-digit code, never six
 * separate requests — plus the resend cooldown countdown, seeded from
 * the server-computed seconds remaining so a page reload never resets
 * the clock early.
 */

function digitsOnly(value) {
    return value.replace(/[^0-9]/g, '');
}

export function initOtpForm() {
    const form = document.querySelector('[data-otp-form]');

    if (!form || form.dataset.otpInitialized === 'true') {
        return;
    }

    form.dataset.otpInitialized = 'true';

    const boxes = Array.from(form.querySelectorAll('[data-otp-inputs] .otp-input'));
    const hidden = form.querySelector('[data-otp-hidden]');

    function syncHidden() {
        if (hidden) {
            hidden.value = boxes.map((box) => box.value).join('');
        }
    }

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            box.value = digitsOnly(box.value).slice(0, 1);
            syncHidden();

            if (box.value && boxes[index + 1]) {
                boxes[index + 1].focus();
            }
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !box.value && boxes[index - 1]) {
                boxes[index - 1].focus();
            }
        });

        box.addEventListener('paste', (event) => {
            const pasted = digitsOnly(event.clipboardData ? event.clipboardData.getData('text') : '');

            if (!pasted) {
                return;
            }

            event.preventDefault();
            pasted.slice(0, boxes.length).split('').forEach((digit, i) => {
                if (boxes[i]) {
                    boxes[i].value = digit;
                }
            });
            syncHidden();

            const next = boxes[Math.min(pasted.length, boxes.length - 1)];

            if (next) {
                next.focus();
            }
        });
    });

    if (boxes[0] && !boxes[0].value) {
        boxes[0].focus();
    }

    form.addEventListener('submit', syncHidden);
}

export function initOtpResend() {
    const trigger = document.querySelector('[data-resend-trigger]');
    const timerEl = document.querySelector('[data-otp-timer]');

    if (!trigger || trigger.dataset.resendInitialized === 'true') {
        return;
    }

    trigger.dataset.resendInitialized = 'true';

    let seconds = parseInt((timerEl && timerEl.dataset.seconds) || '0', 10);

    function render() {
        if (!timerEl) {
            return;
        }

        const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
        const ss = String(seconds % 60).padStart(2, '0');
        timerEl.textContent = `${mm}:${ss}`;
    }

    function tick() {
        seconds -= 1;
        render();

        if (seconds > 0) {
            setTimeout(tick, 1000);
        } else {
            trigger.disabled = false;
        }
    }

    render();

    if (seconds > 0) {
        setTimeout(tick, 1000);
    } else {
        trigger.disabled = false;
    }
}
