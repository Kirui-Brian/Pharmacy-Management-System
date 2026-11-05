import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('auth_token') || null,
        user: null,
        loading: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
        roles: (state) => state.user?.roles || [],
        permissions: (state) => state.user?.permissions || [],
        pharmacy: (state) => state.user?.pharmacy || null,
        branch: (state) => state.user?.branch || null,
        accessibleBranches: (state) => state.user?.accessible_branches || [],
        hasPermission: (state) => (permission) => {
            if (state.user?.roles?.includes('owner') || state.user?.roles?.includes('super-admin')) {
                return true;
            }
            return state.user?.permissions?.includes(permission);
        },
        hasRole: (state) => (role) => {
            return state.user?.roles?.includes(role);
        },
    },
    actions: {
        async login(email, password) {
            this.loading = true;
            try {
                const response = await api.post('/auth/login', { email, password });
                const { token, user } = response.data;
                this.token = token;
                this.user = user;
                localStorage.setItem('auth_token', token);
                return { success: true, user };
            } catch (error) {
                const message = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Login failed';
                return { success: false, message };
            } finally {
                this.loading = false;
            }
        },

        async fetchProfile() {
            if (!this.token) return;
            try {
                const response = await api.get('/auth/me');
                this.user = response.data.user;
            } catch (error) {
                this.logout();
            }
        },

        async switchBranch(branchId) {
            try {
                const response = await api.post('/auth/switch-branch', { branch_id: branchId });
                this.user = response.data.user;
                return { success: true, message: response.data.message };
            } catch (error) {
                return {
                    success: false,
                    message: error.response?.data?.message || 'Could not switch branch',
                };
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await api.post('/auth/logout');
                }
            } catch (e) {
                // Ignore API logout errors and clear state
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
                window.location.href = '/login';
            }
        },
    },
});
