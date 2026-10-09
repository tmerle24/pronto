// iOS only plays audio after a user gesture; unlock on the first tap so the
// "Pronto!" sound can play later without one.
let context = null;

export function unlockAudio() {
    try {
        context ??= new (window.AudioContext || window.webkitAudioContext)();
        if (context.state === 'suspended') context.resume();
        const buffer = context.createBuffer(1, 1, 22050);
        const source = context.createBufferSource();
        source.buffer = buffer;
        source.connect(context.destination);
        source.start(0);
    } catch {
        // no audio, the visual signal still works
    }
}

export function audioContext() {
    return context;
}
