import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
import { ru } from 'vuetify/locale';

const shared = {
    secondary: '#626571',
    accent: '#FFAD4D',
    error: '#E96667',
    success: '#37A878',
    warning: '#FFAD4D',
    'on-primary': '#FFFFFF',
};

export default createVuetify({
    locale: {
        locale: 'ru',
        messages: { ru },
    },
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: { mdi },
    },
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                dark: false,
                colors: {
                    ...shared,
                    primary: '#6957EE',
                    info: '#6957EE',
                    background: '#F5F5F9',
                    surface: '#FFFFFF',
                    'on-surface': '#191827',
                    'on-background': '#191827',
                },
            },
            dark: {
                dark: true,
                colors: {
                    ...shared,
                    primary: '#6957EE',
                    info: '#6957EE',
                    secondary: '#9A9BA3',
                    background: '#141416',
                    surface: '#1E1E22',
                    'on-surface': '#F0F0F3',
                    'on-background': '#F0F0F3',
                },
            },
        },
    },
    defaults: {
        VBtn: { rounded: 'lg', flat: true },
        VCard: { rounded: 'xl', elevation: 0, border: true },
        VChip: { rounded: 'pill' },
        VTextField: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
        VSelect: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
        VTextarea: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
        VDialog: { scrim: 'rgba(27,28,36,.36)' },
    },
    display: {
        mobileBreakpoint: 'md',
    },
});
