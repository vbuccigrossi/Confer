/**
 * WebSocket Service for Real-Time Updates
 *
 * Connects to Laravel Reverb WebSocket server for real-time events:
 * - message.created, message.updated, message.deleted
 * - reaction.added, reaction.removed
 * - user.typing
 * - user.status.changed
 */

type EventCallback = (data: any) => void;

interface WebSocketConfig {
  host: string;
  port: number;
  key: string;
  authEndpoint: string;
}

interface QueuedMessage {
  data: any;
  timestamp: number;
}

class WebSocketService {
  private socket: WebSocket | null = null;
  private config: WebSocketConfig | null = null;
  private token: string | null = null;
  private socketId: string = ''; // Store socket ID from connection_established
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 1000;
  private reconnectTimeout: ReturnType<typeof setTimeout> | null = null;
  private subscriptions: Map<string, Set<EventCallback>> = new Map();
  private subscribedChannels: Set<string> = new Set();
  private isConnecting = false;
  private pingInterval: ReturnType<typeof setInterval> | null = null;
  private messageQueue: QueuedMessage[] = []; // Queue messages during reconnection
  private baseUrl: string = '';

  /**
   * Initialize the WebSocket connection
   */
  async connect(token: string, baseUrl: string): Promise<void> {
    // Clean up any existing connection first
    if (this.socket) {
      this.cleanupSocket();
    }

    if (this.isConnecting) {
      console.log('[WS] Connection already in progress...');
      return;
    }

    this.isConnecting = true;
    this.token = token;
    this.baseUrl = baseUrl;

    // Parse the base URL to construct WebSocket URL
    try {
      const url = new URL(baseUrl);
      const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
      const wsHost = url.hostname;

      // Laravel Reverb typically runs on a different port (8080)
      // Adjust based on your server configuration
      const wsUrl = `${wsProtocol}//${wsHost}:8080/app/confer-key`;

      console.log('[WS] Connecting to:', wsUrl);

      this.socket = new WebSocket(wsUrl);

      this.socket.onopen = this.handleOpen.bind(this);
      this.socket.onmessage = this.handleSocketMessage.bind(this);
      this.socket.onclose = this.handleClose.bind(this);
      this.socket.onerror = this.handleError.bind(this);
    } catch (error) {
      console.error('[WS] Connection error:', error);
      this.isConnecting = false;
      throw error;
    }
  }

  private handleOpen(): void {
    console.log('[WS] Connected');
    this.isConnecting = false;
    this.reconnectAttempts = 0;

    // Start ping interval to keep connection alive
    this.startPing();

    // Re-subscribe to previously subscribed channels
    this.resubscribeChannels();

    // Flush any queued messages
    this.flushMessageQueue();
  }

  private handleSocketMessage(event: MessageEvent): void {
    this.handleMessage(event.data);
  }

  private handleClose(event: CloseEvent): void {
    console.log('[WS] Disconnected:', event.code, event.reason);
    this.isConnecting = false;
    this.socketId = '';
    this.stopPing();

    // Attempt reconnection if not a clean close
    if (event.code !== 1000) {
      this.attemptReconnect();
    }
  }

  private handleError(error: Event): void {
    console.error('[WS] Error:', error);
    this.isConnecting = false;
  }

  /**
   * Clean up socket and event handlers
   */
  private cleanupSocket(): void {
    if (this.socket) {
      // Remove event handlers to prevent memory leaks
      this.socket.onopen = null;
      this.socket.onmessage = null;
      this.socket.onclose = null;
      this.socket.onerror = null;

      // Close if still open
      if (this.socket.readyState === WebSocket.OPEN ||
          this.socket.readyState === WebSocket.CONNECTING) {
        this.socket.close(1000, 'Cleanup');
      }
      this.socket = null;
    }
    this.socketId = '';
  }

  /**
   * Disconnect from WebSocket server
   */
  disconnect(): void {
    // Clear reconnection timeout
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }

    this.stopPing();
    this.cleanupSocket();
    this.subscribedChannels.clear();
    this.subscriptions.clear();
    this.messageQueue = [];
    this.reconnectAttempts = 0;
  }

  /**
   * Subscribe to a private channel
   */
  subscribeToConversation(conversationId: number): void {
    if (!conversationId || conversationId <= 0) return;
    const channel = `private-conversation.${conversationId}`;
    this.subscribeToChannel(channel);
  }

  /**
   * Subscribe to workspace channel for presence/status updates
   */
  subscribeToWorkspace(workspaceId: number): void {
    if (!workspaceId || workspaceId <= 0) return;
    const channel = `private-workspace.${workspaceId}`;
    this.subscribeToChannel(channel);
  }

  /**
   * Unsubscribe from a conversation channel
   */
  unsubscribeFromConversation(conversationId: number): void {
    const channel = `private-conversation.${conversationId}`;
    this.unsubscribeFromChannel(channel);
  }

  /**
   * Subscribe to a channel
   */
  private subscribeToChannel(channel: string): void {
    if (this.subscribedChannels.has(channel)) {
      return;
    }

    this.subscribedChannels.add(channel);

    if (this.socket?.readyState === WebSocket.OPEN && this.socketId) {
      this.sendSubscribe(channel);
    }
  }

  /**
   * Unsubscribe from a channel
   */
  private unsubscribeFromChannel(channel: string): void {
    this.subscribedChannels.delete(channel);

    if (this.socket?.readyState === WebSocket.OPEN) {
      this.send({
        event: 'pusher:unsubscribe',
        data: { channel },
      });
    }
  }

  /**
   * Send subscribe request for a channel
   */
  private async sendSubscribe(channel: string): Promise<void> {
    // For private channels, we need to authenticate
    if (channel.startsWith('private-')) {
      try {
        const auth = await this.authenticateChannel(channel);
        this.send({
          event: 'pusher:subscribe',
          data: {
            channel,
            auth: auth.auth,
          },
        });
      } catch (error) {
        console.error('[WS] Channel auth failed:', channel, error);
        // Retry subscription after a delay
        setTimeout(() => {
          if (this.subscribedChannels.has(channel) && this.socket?.readyState === WebSocket.OPEN) {
            this.sendSubscribe(channel);
          }
        }, 5000);
      }
    } else {
      this.send({
        event: 'pusher:subscribe',
        data: { channel },
      });
    }
  }

  /**
   * Authenticate a private channel with the backend
   */
  private async authenticateChannel(channel: string): Promise<{ auth: string }> {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout

    try {
      const response = await fetch(`${this.getBaseUrl()}/broadcasting/auth`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          socket_id: this.socketId,
          channel_name: channel,
        }),
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`Channel authentication failed: ${response.status}`);
      }

      return response.json();
    } catch (error) {
      clearTimeout(timeoutId);
      throw error;
    }
  }

  /**
   * Register an event listener
   */
  on(event: string, callback: EventCallback): void {
    if (!this.subscriptions.has(event)) {
      this.subscriptions.set(event, new Set());
    }
    this.subscriptions.get(event)!.add(callback);
  }

  /**
   * Remove an event listener
   */
  off(event: string, callback?: EventCallback): void {
    if (!callback) {
      // Remove all listeners for this event
      this.subscriptions.delete(event);
    } else {
      const callbacks = this.subscriptions.get(event);
      if (callbacks) {
        callbacks.delete(callback);
      }
    }
  }

  /**
   * Remove all event listeners
   */
  removeAllListeners(): void {
    this.subscriptions.clear();
  }

  /**
   * Handle incoming WebSocket message
   */
  private handleMessage(data: string): void {
    try {
      const message = JSON.parse(data);
      console.log('[WS] Received:', message.event);

      // Handle Pusher protocol events
      if (message.event === 'pusher:connection_established') {
        const connectionData = JSON.parse(message.data);
        this.socketId = connectionData.socket_id;
        console.log('[WS] Connection established, socket_id:', this.socketId);

        // Now that we have a socket ID, subscribe to channels
        this.resubscribeChannels();
        return;
      }

      if (message.event === 'pusher:pong') {
        // Pong received, connection is alive
        return;
      }

      if (message.event === 'pusher_internal:subscription_succeeded') {
        console.log('[WS] Subscribed to channel:', message.channel);
        return;
      }

      if (message.event === 'pusher:error') {
        console.error('[WS] Server error:', message.data);
        return;
      }

      // Handle application events
      const eventName = message.event;
      let eventData;
      try {
        eventData = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
      } catch {
        eventData = message.data;
      }

      // Dispatch to listeners
      const callbacks = this.subscriptions.get(eventName);
      if (callbacks) {
        callbacks.forEach((callback) => {
          try {
            callback(eventData);
          } catch (err) {
            console.error('[WS] Error in event callback:', err);
          }
        });
      }

      // Also dispatch to wildcard listeners
      const wildcardCallbacks = this.subscriptions.get('*');
      if (wildcardCallbacks) {
        wildcardCallbacks.forEach((callback) => {
          try {
            callback({ event: eventName, data: eventData, channel: message.channel });
          } catch (err) {
            console.error('[WS] Error in wildcard callback:', err);
          }
        });
      }
    } catch (error) {
      console.error('[WS] Message parse error:', error);
    }
  }

  /**
   * Send a message to the WebSocket server
   */
  private send(data: any): void {
    if (this.socket?.readyState === WebSocket.OPEN) {
      this.socket.send(JSON.stringify(data));
    } else {
      // Queue the message for later
      this.messageQueue.push({
        data,
        timestamp: Date.now(),
      });
      console.warn('[WS] Message queued, socket not open');
    }
  }

  /**
   * Flush queued messages after reconnection
   */
  private flushMessageQueue(): void {
    const now = Date.now();
    const maxAge = 30000; // 30 seconds max age for queued messages

    // Filter out old messages and send recent ones
    this.messageQueue = this.messageQueue.filter((item) => {
      if (now - item.timestamp > maxAge) {
        return false; // Drop old messages
      }
      if (this.socket?.readyState === WebSocket.OPEN) {
        this.socket.send(JSON.stringify(item.data));
        return false; // Remove after sending
      }
      return true; // Keep if couldn't send
    });
  }

  /**
   * Re-subscribe to all previously subscribed channels after reconnection
   */
  private resubscribeChannels(): void {
    if (!this.socketId) {
      // Wait for socket ID before subscribing
      return;
    }
    this.subscribedChannels.forEach((channel) => {
      this.sendSubscribe(channel);
    });
  }

  /**
   * Attempt to reconnect after disconnection
   */
  private attemptReconnect(): void {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('[WS] Max reconnection attempts reached');
      return;
    }

    // Clear any existing reconnect timeout
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
    }

    this.reconnectAttempts++;
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);

    console.log(`[WS] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);

    this.reconnectTimeout = setTimeout(() => {
      this.reconnectTimeout = null;
      if (this.token && this.baseUrl) {
        this.connect(this.token, this.baseUrl);
      }
    }, delay);
  }

  /**
   * Start ping interval to keep connection alive
   */
  private startPing(): void {
    this.stopPing(); // Clear any existing interval first
    this.pingInterval = setInterval(() => {
      if (this.socket?.readyState === WebSocket.OPEN) {
        this.send({ event: 'pusher:ping', data: {} });
      }
    }, 30000);
  }

  /**
   * Stop ping interval
   */
  private stopPing(): void {
    if (this.pingInterval) {
      clearInterval(this.pingInterval);
      this.pingInterval = null;
    }
  }

  /**
   * Get the current socket ID
   */
  getSocketId(): string {
    return this.socketId;
  }

  /**
   * Get the base API URL
   */
  private getBaseUrl(): string {
    return this.baseUrl || import.meta.env.VITE_API_URL || 'http://localhost/api';
  }

  /**
   * Check if connected
   */
  isConnected(): boolean {
    return this.socket?.readyState === WebSocket.OPEN && !!this.socketId;
  }

  /**
   * Reset reconnection counter (call after successful user action)
   */
  resetReconnectAttempts(): void {
    this.reconnectAttempts = 0;
  }
}

export const websocketService = new WebSocketService();
export default websocketService;
