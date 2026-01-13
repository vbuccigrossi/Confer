import axios, { AxiosInstance, AxiosError } from 'axios';
import { Device } from '@capacitor/device';

interface User {
  id: number;
  name: string;
  email: string;
  [key: string]: any;
}

interface LoginResponse {
  user: User;
  token?: string;
  existing_session?: boolean;
  message?: string;
}

interface Workspace {
  id: number;
  name: string;
  [key: string]: any;
}

interface Conversation {
  id: number;
  workspace_id: number;
  type: string;
  name?: string;
  display_name?: string;
  [key: string]: any;
}

interface Message {
  id: number;
  conversation_id: number;
  user_id: number;
  body_md: string;
  [key: string]: any;
}

// Maximum file size: 10MB
const MAX_FILE_SIZE = 10 * 1024 * 1024;
// Allowed file types
const ALLOWED_FILE_TYPES = [
  'image/jpeg', 'image/png', 'image/gif', 'image/webp',
  'application/pdf',
  'text/plain', 'text/csv',
  'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

class ApiService {
  private client: AxiosInstance;
  private token: string | null = null;
  private user: User | null = null;
  private baseURL: string;
  private heartbeatFailCount = 0;
  private maxHeartbeatFails = 3;

  constructor() {
    // Use localhost for development, will be configured for production
    this.baseURL = import.meta.env.VITE_API_URL || 'http://localhost/api';

    this.client = axios.create({
      baseURL: this.baseURL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Load saved token
    this.loadToken();

    // Request interceptor to add token
    this.client.interceptors.request.use(
      (config) => {
        if (this.token) {
          config.headers.Authorization = `Bearer ${this.token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

  }

  // Get device information for unique identification
  private async getDeviceInfo(): Promise<{ id: string; name: string }> {
    try {
      const info = await Device.getId();
      const deviceInfo = await Device.getInfo();

      // Create a readable device name with mobile- prefix for analytics
      const name = `mobile-${deviceInfo.platform}-${deviceInfo.model || 'Device'}`;

      return {
        id: info.identifier,
        name: name,
      };
    } catch (error) {
      console.error('[API] Error getting device info:', error);
      // Fallback to a UUID stored in localStorage
      let deviceId = localStorage.getItem('confer_device_id');
      if (!deviceId) {
        deviceId = this.generateUUID();
        localStorage.setItem('confer_device_id', deviceId);
      }
      return {
        id: deviceId,
        name: 'mobile-unknown-Device',
      };
    }
  }

  // Generate a simple UUID for device identification
  private generateUUID(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  // Auth methods
  async login(email: string, password: string, forceNewSession = false): Promise<LoginResponse> {
    const deviceInfo = await this.getDeviceInfo();

    const response = await this.client.post<LoginResponse>('/auth/login', {
      email,
      password,
      device_name: deviceInfo.name,
      device_id: deviceInfo.id,
      force_new_session: forceNewSession,
    });

    // If existing session but no token returned, and we have no stored token, force new session
    if (response.data.existing_session && !response.data.token && !this.token && !forceNewSession) {
      console.log('[API] Existing session but no token, forcing new session');
      return this.login(email, password, true);
    }

    // Save token and user from response
    if (response.data.token) {
      this.token = response.data.token;
    }
    if (response.data.user) {
      this.user = response.data.user;
    }
    this.saveToken();

    return response.data;
  }

  async register(name: string, email: string, password: string): Promise<LoginResponse> {
    const deviceInfo = await this.getDeviceInfo();

    const response = await this.client.post<LoginResponse>('/auth/register', {
      name,
      email,
      password,
      password_confirmation: password,
      device_name: deviceInfo.name,
      device_id: deviceInfo.id,
    });

    if (response.data.token) {
      this.token = response.data.token;
    }
    if (response.data.user) {
      this.user = response.data.user;
    }
    this.saveToken();

    return response.data;
  }

  async logout(): Promise<void> {
    if (this.token) {
      try {
        await this.client.post('/auth/logout');
      } catch (error) {
        console.error('[API] Logout error:', error);
      }
    }
    this.clearToken();
  }

  async getProfile(): Promise<User> {
    const response = await this.client.get<{ user: User }>('/auth/profile');

    // Validate response
    if (!response.data?.user) {
      throw new Error('Invalid profile response');
    }

    this.user = response.data.user;
    return response.data.user;
  }

  // Workspace methods
  async getWorkspaces(): Promise<Workspace[]> {
    const response = await this.client.get<Workspace[]>('/workspaces');
    // Ensure we return an array
    return Array.isArray(response.data) ? response.data : [];
  }

  // Conversation methods
  async getConversations(workspaceId: number): Promise<Conversation[]> {
    if (!workspaceId || workspaceId <= 0) {
      console.warn('[API] Invalid workspace ID:', workspaceId);
      return [];
    }

    const response = await this.client.get<Conversation[]>('/conversations', {
      params: { workspace_id: workspaceId },
    });

    // Ensure we return an array
    return Array.isArray(response.data) ? response.data : [];
  }

  async createChannel(workspaceId: number, name: string, type = 'public_channel', topic = ''): Promise<Conversation> {
    const response = await this.client.post<Conversation>('/conversations', {
      workspace_id: workspaceId,
      type,
      name,
      topic,
    });
    return response.data;
  }

  async createDM(workspaceId: number, userIds: number[]): Promise<Conversation> {
    const response = await this.client.post<Conversation>('/conversations', {
      workspace_id: workspaceId,
      type: userIds.length > 1 ? 'group_dm' : 'dm',
      member_ids: userIds,
    });
    return response.data;
  }

  async searchUsers(workspaceId: number, query: string): Promise<any[]> {
    const response = await this.client.get('/users/search', {
      params: { workspace_id: workspaceId, query },
    });
    // Handle both response formats
    const data = response.data;
    if (Array.isArray(data)) return data;
    if (data?.users && Array.isArray(data.users)) return data.users;
    return [];
  }

  async createBotDM(workspaceId: number, botUserId: number): Promise<Conversation> {
    const response = await this.client.post<Conversation>('/conversations', {
      workspace_id: workspaceId,
      type: 'bot_dm',
      member_ids: [botUserId],
    });
    return response.data;
  }

  async getOrCreateSelfConversation(workspaceId: number): Promise<Conversation> {
    const response = await this.client.get<Conversation>('/conversations/self', {
      params: { workspace_id: workspaceId },
    });
    return response.data;
  }

  async updateConversation(conversationId: number, data: { name?: string; topic?: string }): Promise<Conversation> {
    const response = await this.client.put<Conversation>(`/conversations/${conversationId}`, data);
    return response.data;
  }

  async deleteConversation(conversationId: number): Promise<void> {
    await this.client.delete(`/conversations/${conversationId}`);
  }

  // Message methods
  async getMessages(conversationId: number, limit = 50, before?: number): Promise<{ messages: Message[]; has_more: boolean }> {
    if (!conversationId || conversationId <= 0) {
      return { messages: [], has_more: false };
    }

    const params: any = { limit };
    if (before) params.before = before;

    const response = await this.client.get<{ messages: Message[]; has_more: boolean }>(
      `/conversations/${conversationId}/messages`,
      { params }
    );

    // Validate response structure
    const data = response.data;
    return {
      messages: Array.isArray(data?.messages) ? data.messages : [],
      has_more: !!data?.has_more,
    };
  }

  async sendMessage(conversationId: number, bodyMd: string, parentMessageId?: number): Promise<Message> {
    if (!conversationId || conversationId <= 0) {
      throw new Error('Invalid conversation ID');
    }

    const payload: any = { body_md: bodyMd };
    if (parentMessageId) payload.parent_message_id = parentMessageId;

    const response = await this.client.post<Message>(
      `/conversations/${conversationId}/messages`,
      payload
    );
    return response.data;
  }

  async editMessage(messageId: number, bodyMd: string): Promise<Message> {
    const response = await this.client.patch<Message>(
      `/messages/${messageId}`,
      { body_md: bodyMd }
    );
    return response.data;
  }

  async deleteMessage(messageId: number): Promise<void> {
    await this.client.delete(`/messages/${messageId}`);
  }

  async addReaction(messageId: number, emoji: string): Promise<any> {
    const response = await this.client.post(`/messages/${messageId}/reactions`, {
      emoji,
    });
    return response.data;
  }

  async removeReaction(messageId: number, emoji: string): Promise<void> {
    await this.client.delete(`/messages/${messageId}/reactions/${encodeURIComponent(emoji)}`);
  }

  async markAsRead(messageId: number): Promise<void> {
    if (!messageId || messageId <= 0) return;
    await this.client.post(`/messages/${messageId}/read`);
  }

  // Workspace members
  async getWorkspaceMembers(workspaceId: number): Promise<any[]> {
    const response = await this.client.get<any[]>(
      `/workspaces/${workspaceId}/members`
    );
    return Array.isArray(response.data) ? response.data : [];
  }

  // Search
  async searchMessages(query: string, workspaceId: number, conversationId?: number): Promise<any> {
    const params: any = { q: query, workspace_id: workspaceId };
    if (conversationId) params.conversation_id = conversationId;

    const response = await this.client.get('/search', { params });
    return response.data;
  }

  // Heartbeat for presence
  async sendHeartbeat(): Promise<void> {
    if (!this.token) return;

    try {
      await this.client.post('/auth/heartbeat');
      // Reset fail count on success
      this.heartbeatFailCount = 0;
    } catch (error) {
      this.heartbeatFailCount++;
      console.error('[API] Heartbeat error:', error);

      // If too many consecutive failures, log out
      if (this.heartbeatFailCount >= this.maxHeartbeatFails) {
        console.warn('[API] Too many heartbeat failures, session may be invalid');
        // Don't auto-logout, but the token might be invalid
      }
    }
  }

  // ============================================
  // @Mentions - Search conversation members
  // ============================================
  async searchConversationMembers(conversationId: number, query: string): Promise<any[]> {
    if (!conversationId || conversationId <= 0) {
      return [];
    }

    const response = await this.client.get(
      `/conversations/${conversationId}/members/search`,
      { params: { q: query } }
    );
    return Array.isArray(response.data) ? response.data : [];
  }

  // ============================================
  // User Status/Presence
  // ============================================
  async getStatusPresets(): Promise<any[]> {
    try {
      const response = await this.client.get('/status/presets');
      return Array.isArray(response.data) ? response.data : [];
    } catch (error) {
      console.error('[API] Error fetching status presets:', error);
      return [];
    }
  }

  async getCurrentStatus(): Promise<any> {
    try {
      const response = await this.client.get('/status');
      return response.data;
    } catch (error) {
      console.error('[API] Error fetching current status:', error);
      return null;
    }
  }

  async setStatus(data: {
    status?: string;
    message?: string;
    emoji?: string;
    expires_in?: number | null;
  }): Promise<any> {
    const response = await this.client.put('/status', data);
    return response.data;
  }

  async clearStatus(): Promise<void> {
    await this.client.delete('/status');
  }

  async enableDND(expiresIn?: number): Promise<any> {
    const response = await this.client.post('/status/dnd', {
      expires_in: expiresIn,
    });
    return response.data;
  }

  async disableDND(): Promise<void> {
    await this.client.delete('/status/dnd');
  }

  // ============================================
  // File Upload
  // ============================================
  validateFile(file: File): { valid: boolean; error?: string } {
    if (file.size > MAX_FILE_SIZE) {
      return {
        valid: false,
        error: `File too large. Maximum size is ${MAX_FILE_SIZE / 1024 / 1024}MB`,
      };
    }

    if (!ALLOWED_FILE_TYPES.includes(file.type) && file.type !== '') {
      return {
        valid: false,
        error: 'File type not allowed. Please upload images, PDFs, or documents.',
      };
    }

    return { valid: true };
  }

  async uploadFile(
    file: File,
    conversationId: number,
    messageId?: number,
    onProgress?: (progress: number) => void,
    abortSignal?: AbortSignal
  ): Promise<any> {
    // Validate file first
    const validation = this.validateFile(file);
    if (!validation.valid) {
      throw new Error(validation.error);
    }

    if (!conversationId || conversationId <= 0) {
      throw new Error('Invalid conversation ID');
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('conversation_id', conversationId.toString());
    if (messageId) {
      formData.append('message_id', messageId.toString());
    }

    const response = await this.client.post('/files', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      timeout: 120000, // 2 minute timeout for file uploads
      signal: abortSignal,
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          onProgress(progress);
        }
      },
    });
    return response.data;
  }

  async sendMessageWithAttachments(
    conversationId: number,
    bodyMd: string,
    attachmentIds: number[],
    parentMessageId?: number
  ): Promise<Message> {
    if (!conversationId || conversationId <= 0) {
      throw new Error('Invalid conversation ID');
    }

    const payload: any = {
      body_md: bodyMd,
      attachment_ids: attachmentIds,
    };
    if (parentMessageId) payload.parent_message_id = parentMessageId;

    const response = await this.client.post<Message>(
      `/conversations/${conversationId}/messages`,
      payload
    );
    return response.data;
  }

  async getFileDownloadUrl(fileId: number): Promise<string> {
    const response = await this.client.get(`/files/${fileId}`);
    return response.data?.url || '';
  }

  // Get thread replies specifically
  async getThreadReplies(conversationId: number, parentMessageId: number): Promise<Message[]> {
    if (!conversationId || !parentMessageId) {
      return [];
    }

    try {
      const response = await this.client.get<{ messages: Message[] }>(
        `/conversations/${conversationId}/messages`,
        { params: { parent_message_id: parentMessageId, limit: 100 } }
      );
      return Array.isArray(response.data?.messages) ? response.data.messages : [];
    } catch (error) {
      console.error('[API] Error fetching thread replies:', error);
      return [];
    }
  }

  // Token management
  private saveToken(): void {
    try {
      if (this.token) {
        localStorage.setItem('confer_token', this.token);
      }
      if (this.user) {
        localStorage.setItem('confer_user', JSON.stringify(this.user));
      }
    } catch (error) {
      console.error('[API] Error saving token:', error);
    }
  }

  private loadToken(): void {
    try {
      const token = localStorage.getItem('confer_token');
      const userStr = localStorage.getItem('confer_user');

      if (token) {
        this.token = token;
      }

      if (userStr) {
        try {
          const parsed = JSON.parse(userStr);
          // Validate parsed user has required fields
          if (parsed && typeof parsed.id === 'number') {
            this.user = parsed;
          } else {
            console.warn('[API] Invalid user data in storage, clearing');
            localStorage.removeItem('confer_user');
          }
        } catch (parseError) {
          console.error('[API] Error parsing user data:', parseError);
          localStorage.removeItem('confer_user');
        }
      }
    } catch (error) {
      console.error('[API] Error loading token:', error);
    }
  }

  private clearToken(): void {
    this.token = null;
    this.user = null;
    this.heartbeatFailCount = 0;
    try {
      localStorage.removeItem('confer_token');
      localStorage.removeItem('confer_user');
    } catch (error) {
      console.error('[API] Error clearing token:', error);
    }
  }

  // Getters
  isAuthenticated(): boolean {
    return !!this.token;
  }

  getUser(): User | null {
    return this.user;
  }

  getToken(): string | null {
    return this.token;
  }

  setBaseURL(url: string): void {
    this.baseURL = url;
    this.client.defaults.baseURL = url;
  }

  getBaseURL(): string {
    return this.baseURL;
  }

  // Expose client for advanced usage
  getClient(): AxiosInstance {
    return this.client;
  }
}

export const api = new ApiService();
export default api;
