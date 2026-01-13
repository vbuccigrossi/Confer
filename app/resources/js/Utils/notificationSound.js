/**
 * Generate and play a pleasant notification sound using Web Audio API
 */
export function playNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();

        // Create oscillators for a pleasant two-tone notification
        const oscillator1 = audioContext.createOscillator();
        const oscillator2 = audioContext.createOscillator();

        // Create gain nodes for volume control
        const gainNode1 = audioContext.createGain();
        const gainNode2 = audioContext.createGain();
        const masterGain = audioContext.createGain();

        // Set frequencies (a pleasant E and B note combination)
        oscillator1.frequency.value = 659.25; // E5
        oscillator2.frequency.value = 987.77; // B5

        // Use sine wave for a soft, pleasant tone
        oscillator1.type = 'sine';
        oscillator2.type = 'sine';

        // Connect nodes
        oscillator1.connect(gainNode1);
        oscillator2.connect(gainNode2);
        gainNode1.connect(masterGain);
        gainNode2.connect(masterGain);
        masterGain.connect(audioContext.destination);

        // Set initial volume (quieter, less annoying)
        masterGain.gain.value = 0.15;

        // Envelope for first tone
        gainNode1.gain.setValueAtTime(0, audioContext.currentTime);
        gainNode1.gain.linearRampToValueAtTime(1, audioContext.currentTime + 0.01);
        gainNode1.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

        // Envelope for second tone (starts slightly after first)
        gainNode2.gain.setValueAtTime(0, audioContext.currentTime);
        gainNode2.gain.setValueAtTime(0, audioContext.currentTime + 0.05);
        gainNode2.gain.linearRampToValueAtTime(1, audioContext.currentTime + 0.06);
        gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

        // Start and stop oscillators
        const startTime = audioContext.currentTime;
        const duration = 0.3;

        oscillator1.start(startTime);
        oscillator1.stop(startTime + duration);

        oscillator2.start(startTime + 0.05);
        oscillator2.stop(startTime + duration);

        // Clean up after playing
        setTimeout(() => {
            try {
                audioContext.close();
            } catch (e) {
                // Ignore cleanup errors
            }
        }, duration * 1000 + 100);

    } catch (error) {
        console.error('Error playing notification sound:', error);
    }
}
