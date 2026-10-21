import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */
import type { TeamInvitationContext } from '@/types';

type Props = { status?: string; canResetPassword: boolean; teamInvitation?: TeamInvitationContext | null };

export default function Login({ status, canResetPassword, teamInvitation }: Props) {
    return <>
        <Head title="Connexion" />
        {teamInvitation && <TeamInvitationAlert invitation={teamInvitation} action="Log in" />}
        {/* @chisel-passkeys */}
        <PasskeyVerify />
        {/* @end-chisel-passkeys */}
        <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-6">
            {({ processing, errors }) => {
                const credentialError = errors.email || errors.password;
                return <>
                    {credentialError && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">Adresse e-mail ou mot de passe incorrect. Vérifiez vos identifiants et réessayez.</p>}
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Adresse e-mail</Label>
                            <Input id="email" type="email" name="email" required autoFocus tabIndex={1} autoComplete="email" placeholder="nom@exemple.com" />
                            {errors.email && !credentialError ? <InputError message={errors.email} /> : null}
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Mot de passe</Label>
                                {canResetPassword && <TextLink href={request()} className="ml-auto text-sm" tabIndex={5}>Mot de passe oublié ?</TextLink>}
                            </div>
                            <PasswordInput id="password" name="password" required tabIndex={2} autoComplete="current-password" placeholder="Votre mot de passe" />
                            {errors.password && !credentialError ? <InputError message={errors.password} /> : null}
                        </div>
                        <div className="flex items-center space-x-3"><Checkbox id="remember" name="remember" tabIndex={3} /><Label htmlFor="remember">Rester connecté</Label></div>
                        <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing} data-test="login-button">{processing && <Spinner />}{processing ? 'Connexion en cours…' : 'Se connecter'}</Button>
                    </div>
                    {/* @chisel-registration */}
                    <div className="text-center text-sm text-muted-foreground">Vous n’avez pas encore de compte ?{' '}<TextLink href={register({ query: { invitation: teamInvitation?.code } })} data-test="register-link" tabIndex={5}>Créer un compte</TextLink></div>
                    {/* @end-chisel-registration */}
                </>;
            }}
        </Form>
        {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
    </>;
}

Login.layout = { title: 'Connexion à SIGEC', description: 'Saisissez votre adresse e-mail et votre mot de passe pour accéder à la gestion carcérale.' };
