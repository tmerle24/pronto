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

// short two-tone chime, no audio files needed
export function chime(frequencies = [880, 1320], duration = 0.18) {
    if (!context) return;
    const start = context.currentTime;
    frequencies.forEach((frequency, i) => {
        const osc = context.createOscillator();
        const gain = context.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequency;
        const t0 = start + i * duration;
        gain.gain.setValueAtTime(0.0001, t0);
        gain.gain.exponentialRampToValueAtTime(0.5, t0 + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
        osc.connect(gain).connect(context.destination);
        osc.start(t0);
        osc.stop(t0 + duration + 0.05);
    });
}
