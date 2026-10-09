import { audioContext, chime } from './sound';

// The "Pronto!" moment: sound, vibration, blinking title.
let blinkTimer = null;
let soundTimer = null;

export function startPronto(number) {
    const original = document.title;
    let on = false;
    blinkTimer = setInterval(() => {
        on = !on;
        document.title = on ? `PRONTO! Nr. ${number}` : original;
    }, 900);

    navigator.vibrate?.([400, 200, 400, 200, 800]);

    const ring = () => chime([784, 988, 1175, 1568], 0.22);
    if (audioContext()) {
        ring();
        let count = 1;
        soundTimer = setInterval(() => {
            if (++count > 4) return clearInterval(soundTimer);
            ring();
            navigator.vibrate?.([400, 200, 400]);
        }, 4000);
    }

    return () => stopPronto(original);
}

export function stopPronto(title) {
    clearInterval(blinkTimer);
    clearInterval(soundTimer);
    if (title) document.title = title;
}
