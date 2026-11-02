import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initOtpForm, initOtpResend } from './otp.js';

/**
 * Coverage for the "Verifica tu correo" OTP boxes: auto-advance,
 * backspace-to-previous, paste-splits-the-code, digits-only input, the
 * single hidden field the real POST actually sends, and the
 * server-seeded resend cooldown countdown.
 */

function buildOtpForm() {
    document.body.innerHTML = `
        <form data-otp-form>
            <div data-otp-inputs>
                <input class="otp-input" maxlength="1">
                <input class="otp-input" maxlength="1">
                <input class="otp-input" maxlength="1">
                <input class="otp-input" maxlength="1">
                <input class="otp-input" maxlength="1">
                <input class="otp-input" maxlength="1">
            </div>
            <input type="hidden" data-otp-hidden>
        </form>
    `;

    return document.querySelector('form');
}

describe('initOtpForm', () => {
    let boxes;
    let hidden;

    beforeEach(() => {
        const form = buildOtpForm();
        initOtpForm();
        boxes = Array.from(form.querySelectorAll('.otp-input'));
        hidden = form.querySelector('[data-otp-hidden]');
    });

    it('autofocuses the first box on init', () => {
        expect(document.activeElement).toBe(boxes[0]);
    });

    it('auto-advances focus to the next box as digits are typed', () => {
        boxes[0].value = '4';
        boxes[0].dispatchEvent(new Event('input'));

        expect(document.activeElement).toBe(boxes[1]);
    });

    it('strips non-numeric characters', () => {
        boxes[0].value = 'a';
        boxes[0].dispatchEvent(new Event('input'));

        expect(boxes[0].value).toBe('');
    });

    it('moves focus back to the previous box on backspace from an empty box', () => {
        boxes[1].focus();
        boxes[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'Backspace' }));

        expect(document.activeElement).toBe(boxes[0]);
    });

    it('distributes a pasted 6-digit code across all six boxes in one go', () => {
        const event = new Event('paste', { cancelable: true });
        event.clipboardData = { getData: () => '123456' };
        boxes[0].dispatchEvent(event);

        expect(boxes.map((box) => box.value).join('')).toBe('123456');
    });

    it('keeps the single hidden field in sync — the real submit sends one code, not six requests', () => {
        '123456'.split('').forEach((digit, index) => {
            boxes[index].value = digit;
            boxes[index].dispatchEvent(new Event('input'));
        });

        expect(hidden.value).toBe('123456');
    });
});

describe('initOtpResend', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    it('stays disabled until the server-seeded cooldown reaches zero, then re-enables', () => {
        document.body.innerHTML = `
            <button data-resend-trigger disabled></button>
            <span data-otp-timer data-seconds="2">00:02</span>
        `;

        initOtpResend();

        const trigger = document.querySelector('[data-resend-trigger]');
        const timer = document.querySelector('[data-otp-timer]');

        expect(timer.textContent).toBe('00:02');

        vi.advanceTimersByTime(1000);
        expect(timer.textContent).toBe('00:01');
        expect(trigger.disabled).toBe(true);

        vi.advanceTimersByTime(1000);
        expect(timer.textContent).toBe('00:00');
        expect(trigger.disabled).toBe(false);

        vi.useRealTimers();
    });

    it('is already enabled when the server reports no cooldown left', () => {
        document.body.innerHTML = `
            <button data-resend-trigger disabled></button>
            <span data-otp-timer data-seconds="0">00:00</span>
        `;

        initOtpResend();

        expect(document.querySelector('[data-resend-trigger]').disabled).toBe(false);
        vi.useRealTimers();
    });
});
