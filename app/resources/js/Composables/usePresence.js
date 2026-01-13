import { onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export function usePresence() {
    let heartbeatInterval = null;

    const sendHeartbeat = async () => {
        try {
            await axios.post('/api/auth/heartbeat');
        } catch (error) {
            console.error('Failed to send heartbeat:', error);
        }
    };

    const startHeartbeat = () => {
        // Send initial heartbeat
        sendHeartbeat();

        // Send heartbeat every 2 minutes
        heartbeatInterval = setInterval(sendHeartbeat, 120000);
    };

    const stopHeartbeat = () => {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    };

    onMounted(() => {
        startHeartbeat();
    });

    onUnmounted(() => {
        stopHeartbeat();
    });

    return {
        sendHeartbeat,
        startHeartbeat,
        stopHeartbeat,
    };
}
