import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notification', {
    state: () => ({
        visible: false,
        text: '',
        color: 'success',
        timeout: 4000,
    }),
    actions: {
        show(text, color = 'success', timeout = 4000) {
            this.text = text;
            this.color = color;
            this.timeout = timeout;
            this.visible = true;
        },
        success(text) {
            this.show(text, 'success');
        },
        error(text) {
            this.show(text, 'error', 6000);
        },
        warning(text) {
            this.show(text, 'warning');
        },
        info(text) {
            this.show(text, 'info');
        },
    },
});
