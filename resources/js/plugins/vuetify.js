import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

const afyaTheme = {
    dark: false,
    colors: {
        background: '#F8FAFC',
        surface: '#FFFFFF',
        primary: '#00897B',      // Healthcare Teal
        'primary-darken-1': '#00695C',
        secondary: '#1E88E5',    // Clinical Blue
        'secondary-darken-1': '#1565C0',
        accent: '#00ACC1',
        error: '#D32F2F',        // Alert Red
        info: '#0288D1',
        success: '#2E7D32',      // Fresh Safe Green
        warning: '#F57C00',      // Expiry Warning Amber
    },
};

export default createVuetify({
    theme: {
        defaultTheme: 'afyaTheme',
        themes: {
            afyaTheme,
        },
    },
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
        },
    },
});
