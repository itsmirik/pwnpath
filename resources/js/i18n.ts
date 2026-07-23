import { createI18n } from 'vue-i18n';

export type Locale = 'ru' | 'uz' | 'en';

const SUPPORTED_LOCALES: Locale[] = ['ru', 'uz', 'en'];
const DEFAULT_LOCALE: Locale = 'ru';
const FALLBACK_LOCALE: Locale = 'en';

type MessageTree = Record<string, unknown>;
type LocaleMessages = Record<string, MessageTree>;
type AllMessages = Record<Locale, LocaleMessages>;

const modules = import.meta.glob<{ default: MessageTree }>(
    '../lang/**/*.json',
    { eager: true },
);

const messages: AllMessages = { ru: {}, uz: {}, en: {} };

for (const path in modules) {
    // path shape: ../lang/{locale}/{module}/{feature}.json
    const match = path.match(
        /\/lang\/([^/]+)\/([^/]+)\/([^/]+)\.json$/,
    );
    if (!match) continue;

    const [, locale, module, feature] = match;
    if (!SUPPORTED_LOCALES.includes(locale as Locale)) continue;

    const loc = locale as Locale;
    messages[loc][module] ??= {};
    (messages[loc][module] as MessageTree)[feature] = modules[path].default;
}

function detectLocale(): Locale {
    if (typeof document !== 'undefined') {
        const htmlLang = document.documentElement.lang?.split('-')[0];
        if (SUPPORTED_LOCALES.includes(htmlLang as Locale)) {
            return htmlLang as Locale;
        }
        const cookieMatch = document.cookie.match(/(?:^|;\s*)locale=([^;]+)/);
        if (cookieMatch && SUPPORTED_LOCALES.includes(cookieMatch[1] as Locale)) {
            return cookieMatch[1] as Locale;
        }
    }
    return DEFAULT_LOCALE;
}

export const i18n = createI18n<false>({
    legacy: false,
    locale: detectLocale(),
    fallbackLocale: FALLBACK_LOCALE,
    messages,
    missingWarn: false,
    fallbackWarn: false,
});

export { SUPPORTED_LOCALES, DEFAULT_LOCALE, FALLBACK_LOCALE };
