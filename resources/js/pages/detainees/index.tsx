import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Detainee = {
    id: number;
    record_number: string;
    family_name: string;
    post_name: string | null;
    given_names: string;
    date_of_birth: string | null;
    sex: string | null;
    current_stay: {
        establishment: { name: string };
        cell_assignments: {
            cell: {
                code: string;
                name: string | null;
                building: { code: string; name: string };
            };
        }[];
    } | null;
    cases: { case_type: string }[];
};
type Props = {
    detainees: {
        data: Detainee[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    canCreate: boolean;
    search: string;
};

function ReleaseForm({ id }: { id: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        release_reason: 'release_order',
        release_authority_reference: '',
        release_authority: '',
        released_at: new Date(
            Date.now() - new Date().getTimezoneOffset() * 60000,
        )
            .toISOString()
            .slice(0, 16),
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/detainees/${id}/release`, {
            onSuccess: () => setOpen(false),
        });
    }
    if (!open)
        return (
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setOpen(true)}
            >
                Enregistrer la libération
            </Button>
        );
    return (
        <form onSubmit={submit} className="min-w-64 space-y-2">
            <label className="block text-xs">
                Motif
                <select
                    required
                    className="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                    value={form.data.release_reason}
                    onChange={(event) =>
                        form.setData('release_reason', event.target.value)
                    }
                >
                    <option value="release_order">Ordre de libération</option>
                    <option value="sentence_completed">Peine exécutée</option>
                    <option value="conditional_release">
                        Libération conditionnelle
                    </option>
                    <option value="other">Autre décision</option>
                </select>
            </label>
            <label className="block text-xs">
                Autorité compétente
                <input
                    required
                    maxLength={255}
                    className="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                    value={form.data.release_authority}
                    onChange={(event) =>
                        form.setData('release_authority', event.target.value)
                    }
                />
            </label>
            <label className="block text-xs">
                Référence de la décision
                <input
                    required
                    maxLength={255}
                    className="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                    value={form.data.release_authority_reference}
                    onChange={(event) =>
                        form.setData(
                            'release_authority_reference',
                            event.target.value,
                        )
                    }
                />
            </label>
            <label className="block text-xs">
                Date et heure
                <input
                    required
                    type="datetime-local"
                    className="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                    value={form.data.released_at}
                    onChange={(event) =>
                        form.setData('released_at', event.target.value)
                    }
                />
            </label>
            {form.hasErrors ? (
                <p className="text-xs text-destructive">
                    Vérifiez les champs obligatoires et la date de libération.
                </p>
            ) : null}
            <div className="flex gap-2">
                <Button size="sm" disabled={form.processing}>
                    Confirmer
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => setOpen(false)}
                >
                    Annuler
                </Button>
            </div>
        </form>
    );
}

export default function DetaineesIndex({
    detainees,
    canCreate,
    search,
}: Props) {
    const [query, setQuery] = useState(search);
    useEffect(() => {
        setQuery(search);
    }, [search]);
    useEffect(() => {
        const timer = window.setTimeout(
            () =>
                router.get(
                    '/detainees',
                    query.trim() ? { search: query.trim() } : {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            250,
        );
        return () => window.clearTimeout(timer);
    }, [query]);
    return (
        <>
            <Head title="Personnes détenues" />
            <div className="flex flex-col space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Personnes détenues"
                        description="Dossiers actifs accessibles selon votre affectation."
                    />
                    {canCreate ? (
                        <Button asChild>
                            <Link href="/detainees/create">
                                Enregistrer une admission
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <input
                        aria-label="Rechercher une personne détenue"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Rechercher par numéro de dossier ou nom"
                        className="w-full max-w-lg rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <span className="text-sm text-muted-foreground">
                        {detainees.total} dossier(s) actif(s)
                    </span>
                </div>
                {detainees.data.length === 0 ? (
                    <p className="rounded-lg border p-6 text-center text-muted-foreground">
                        Aucun dossier actif ne correspond à la recherche.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        N° dossier
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nom
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Établissement
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Bâtiment / cellule
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Statut judiciaire
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {detainees.data.map((person) => {
                                    const assignment =
                                        person.current_stay
                                            ?.cell_assignments[0];
                                    return (
                                        <tr
                                            key={person.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3">
                                                {
                                                    person.record_number.split(
                                                        '-',
                                                    )[0]
                                                }
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                {person.family_name},{' '}
                                                {person.given_names}
                                            </td>
                                            <td className="px-4 py-3">
                                                {person.current_stay
                                                    ?.establishment.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {assignment
                                                    ? `${assignment.cell.building.code} — ${assignment.cell.code}${assignment.cell.name ? ` · ${assignment.cell.name}` : ''}`
                                                    : 'Aucune affectation'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {person.cases[0]?.case_type ===
                                                'pretrial'
                                                    ? 'Prévenu'
                                                    : 'Condamné'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/detainees/${person.id}`}
                                                        >
                                                            Ouvrir le dossier
                                                        </Link>
                                                    </Button>
                                                    <ReleaseForm
                                                        id={person.id}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <nav
                    aria-label="Pages des dossiers détenus"
                    className="flex flex-wrap gap-1"
                >
                    {detainees.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveState
                                preserveScroll
                                className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-accent'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={index}
                                className="rounded border px-3 py-1.5 text-sm text-muted-foreground"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            </div>
        </>
    );
}
DetaineesIndex.layout = {
    breadcrumbs: [{ title: 'Personnes détenues', href: '/detainees' }],
};
