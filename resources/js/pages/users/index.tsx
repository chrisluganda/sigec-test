import { FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Establishment = { id: number; code: string; name: string };
type Access = Establishment & { pivot: { role: string; is_active: boolean } };
type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_central_admin: boolean;
    establishments: Access[];
};
type Props = {
    users: ManagedUser[];
    establishments: Establishment[];
    success: string | null;
};
type Role = 'director' | 'agent' | 'judicial' | 'custody' | 'medical';
const roleNames: Record<Role, string> = {
    director: 'Directeur',
    agent: 'Agent',
    judicial: 'Agent judiciaire',
    custody: 'Agent de garde',
    medical: 'Personnel médical',
};

function AccessForm({
    user,
    establishments,
}: {
    user: ManagedUser;
    establishments: Establishment[];
}) {
    const form = useForm<{ establishment_id: number | ''; role: Role }>({
        establishment_id:
            establishments.length === 1 ? establishments[0].id : '',
        role: 'agent',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/users/${user.id}/access`);
    }
    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <label className="text-xs">
                Établissement
                <select
                    required
                    value={form.data.establishment_id}
                    onChange={(e) =>
                        form.setData('establishment_id', Number(e.target.value))
                    }
                    className="mt-1 block rounded-md border bg-background px-2 py-2 text-sm"
                >
                    <option value="">Choisir</option>
                    {establishments.map((x) => (
                        <option key={x.id} value={x.id}>
                            {x.code} — {x.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="text-xs">
                Rôle
                <select
                    value={form.data.role}
                    onChange={(e) =>
                        form.setData('role', e.target.value as Role)
                    }
                    className="mt-1 block rounded-md border bg-background px-2 py-2 text-sm"
                >
                    {Object.entries(roleNames).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={form.processing}
            >
                Changer d’établissement
            </Button>
            <small className="basis-full text-muted-foreground">
                Le changement désactive immédiatement l’accès aux anciens
                établissements.
            </small>
            {form.errors.establishment_id ? (
                <span className="text-xs text-destructive">
                    {form.errors.establishment_id}
                </span>
            ) : null}
        </form>
    );
}

export default function UsersIndex({ users, establishments, success }: Props) {
    const form = useForm<{
        name: string;
        email: string;
        password: string;
        establishment_id: number | '';
        role: Role;
    }>({
        name: '',
        email: '',
        password: '',
        establishment_id:
            establishments.length === 1 ? establishments[0].id : '',
        role: 'agent',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/users', {
            onSuccess: () => form.reset('name', 'email', 'password'),
        });
    }
    return (
        <>
            <Head title="Utilisateurs SIGEC" />
            <div className="mx-auto flex max-w-6xl flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Utilisateurs SIGEC"
                        description="Créez les comptes locaux et attribuez les accès aux établissements."
                    />
                    <Button variant="outline" asChild>
                        <Link href="/establishments">
                            Retour aux établissements
                        </Link>
                    </Button>
                </div>
                {success ? (
                    <p className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                        {success}
                    </p>
                ) : null}
                `r`n{' '}
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-lg border p-5 md:grid-cols-2"
                >
                    <h2 className="font-semibold md:col-span-2">
                        Créer un utilisateur local
                    </h2>
                    <label className="text-sm">
                        Nom complet
                        <input
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                        {form.errors.name && (
                            <small className="text-destructive">
                                {form.errors.name}
                            </small>
                        )}
                    </label>
                    <label className="text-sm">
                        Adresse e-mail (identifiant de connexion)
                        <input
                            required
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                        {form.errors.email && (
                            <small className="text-destructive">
                                {form.errors.email}
                            </small>
                        )}
                        <small className="mt-1 block text-muted-foreground">
                            Aucun lien de vérification ne sera envoyé.
                        </small>
                    </label>
                    <label className="text-sm">
                        Mot de passe provisoire
                        <input
                            required
                            type="password"
                            minLength={8}
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                        {form.errors.password && (
                            <small className="text-destructive">
                                {form.errors.password}
                            </small>
                        )}
                    </label>
                    <label className="text-sm">
                        Établissement
                        <select
                            required
                            value={form.data.establishment_id}
                            onChange={(e) =>
                                form.setData(
                                    'establishment_id',
                                    Number(e.target.value),
                                )
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        >
                            <option value="">Choisir un établissement</option>
                            {establishments.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.code} — {x.name}
                                </option>
                            ))}
                        </select>
                        {form.errors.establishment_id && (
                            <small className="text-destructive">
                                {form.errors.establishment_id}
                            </small>
                        )}
                    </label>
                    <label className="text-sm">
                        Rôle
                        <select
                            value={form.data.role}
                            onChange={(e) =>
                                form.setData('role', e.target.value as Role)
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        >
                            {Object.entries(roleNames).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="md:col-span-2">
                        <Button
                            type="submit"
                            disabled={
                                form.processing || establishments.length === 0
                            }
                        >
                            {form.processing
                                ? 'Création…'
                                : 'Créer le compte et attribuer l’accès'}
                        </Button>
                        {establishments.length === 0 && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Créez d’abord un établissement actif.
                            </p>
                        )}
                    </div>
                </form>
                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Comptes enregistrés"
                        description={`${users.length} compte(s)`}
                    />
                    {users.length === 0 ? (
                        <p className="rounded-lg border p-6 text-center text-muted-foreground">
                            Aucun compte utilisateur.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Utilisateur
                                        </th>
                                        <th className="px-4 py-3">État</th>
                                        <th className="px-4 py-3">
                                            Accès attribués
                                        </th>
                                        <th className="px-4 py-3">
                                            Ajouter ou modifier un accès
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-t align-top"
                                        >
                                            <td className="px-4 py-4">
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {user.email}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {user.is_central_admin
                                                    ? 'Administrateur central'
                                                    : user.is_active
                                                      ? 'Actif'
                                                      : 'Désactivé'}
                                                {!user.is_central_admin && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="mt-2 block"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/users/${user.id}/status`,
                                                                {
                                                                    is_active:
                                                                        !user.is_active,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {user.is_active
                                                            ? 'Désactiver'
                                                            : 'Activer'}
                                                    </Button>
                                                )}
                                            </td>
                                            <td className="space-y-1 px-4 py-4">
                                                {user.establishments.length
                                                    ? user.establishments.map(
                                                          (access) => (
                                                              <div
                                                                  key={
                                                                      access.id
                                                                  }
                                                              >
                                                                  {access.code}{' '}
                                                                  —{' '}
                                                                  {access.name}
                                                                  <span className="text-muted-foreground">
                                                                      {' '}
                                                                      ·{' '}
                                                                      {roleNames[
                                                                          access
                                                                              .pivot
                                                                              .role as Role
                                                                      ] ??
                                                                          access
                                                                              .pivot
                                                                              .role}
                                                                      {access
                                                                          .pivot
                                                                          .is_active
                                                                          ? ''
                                                                          : ' · désactivé'}
                                                                  </span>
                                                              </div>
                                                          ),
                                                      )
                                                    : 'Aucun'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {!user.is_central_admin && (
                                                    <AccessForm
                                                        user={user}
                                                        establishments={
                                                            establishments
                                                        }
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
UsersIndex.layout = {
    breadcrumbs: [{ title: 'Utilisateurs', href: '/users' }],
};
