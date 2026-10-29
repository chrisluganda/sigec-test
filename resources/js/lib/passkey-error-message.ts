export function translatePasskeyError(message: string | null): string | null {
    if (!message) return null;

    const translations: Record<string, string> = {
        'Passkeys are not supported in this browser.': 'Les clés d’accès ne sont pas prises en charge par ce navigateur.',
        'The passkey operation was cancelled.': 'L’opération avec la clé d’accès a été annulée.',
        'This device is already registered as a passkey.': 'Cet appareil est déjà enregistré comme clé d’accès.',
        'An unknown error occurred.': 'Une erreur inconnue est survenue pendant l’opération.',
        'The given data was invalid.': 'Les informations saisies ne sont pas valides.',
        'This action is unauthorized.': 'Vous n’êtes pas autorisé à effectuer cette action.',
        'Unauthenticated.': 'Votre session a expiré. Reconnectez-vous.',
    };

    const translated = translations[message];
    if (translated) return translated;

    const invalidDomain = message.match(/^Passkeys can't be used on (.+)\. For local development, use localhost\.$/);
    if (invalidDomain) {
        return `Les clés d’accès ne peuvent pas être utilisées sur ${invalidDomain[1]}. En local, utilisez « localhost ».`;
    }

    return 'Une erreur est survenue lors de l’utilisation de la clé d’accès. Réessayez ou choisissez une autre méthode de connexion.';
}