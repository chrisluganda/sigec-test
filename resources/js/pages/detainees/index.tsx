import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Detainee = {
    id: number;
    record_number: string;
    family_name: string;
    post_name: string | null;
    given_names: string;
    date_of_birth: string | null;
    sex: string | null;
    current_stay: {
        admitted_at: string;
        establishment: { name: string };
        cell_assignments: {
            cell: {
                code: string;
                name: string | null;
                building: { code: string; name: string };
            };
        }[];
    } | null;
    latest_stay: {
        admitted_at: string;
        released_at: string | null;
        establishment: { name: string };
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
    status: 'active' | 'released';
};

function ReleaseForm({ id, admittedAt }: { id: number; admittedAt: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        release_reason: 'release_order',
        release_details: '',
        release_authority_reference: '',
        release_authority: '',
        released_at: new Date(
            Date.now() - new Date().getTimezoneOffset() * 60000,
        )
            .toISOString()
            .slice(0, 19),
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            released_at: new Date(data.released_at).toISOString(),
        }));
        form.post(`/detainees/${id}/release`, {
            onSuccess: () => setOpen(false),
        });
    }
    const error = (field: keyof typeof form.errors) =>
        form.errors[field] ? (
            <span className="mt-1 block text-sm text-destructive">
                {form.errors[field]}
            </span>
        ) : null;
    const formatDateTimeLocal = (date: Date) =>
        new Date(date.getTime() - date.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 19);
    const minReleaseDate = formatDateTimeLocal(
        new Date(new Date(admittedAt).getTime() + 1000),
    );
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="secondary" size="sm">
                    Enregistrer la libération
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Enregistrer la libération</DialogTitle>
                    <DialogDescription>
                        Les champs marqués d’un astérisque sont obligatoires.
                        La cellule sera libérée après confirmation.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <label className="block text-sm font-medium">
                        Motif *
                        <select
                            required
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm font-normal"
                            value={form.data.release_reason}
                            onChange={(event) =>
                                form.setData('release_reason', event.target.value)
                            }
                        >
                            <option value="release_order">Ordre de libération</option>
                            <option value="sentence_completed">Peine exécutée</option>
                            <option value="conditional_release">Libération conditionnelle</option>
                            <option value="other">Autre décision</option>
                        </select>
                        {error('release_reason')}
                    </label>
                    <label className="block text-sm font-medium">
                        Précisions sur le motif{' '}
                        {form.data.release_reason === 'other' ? '*' : '(facultatif)'}
                        <textarea
                            maxLength={1000}
                            rows={3}
                            required={form.data.release_reason === 'other'}
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm font-normal"
                            placeholder="Précisez le motif de la libération"
                            value={form.data.release_details}
                            onChange={(event) =>
                                form.setData('release_details', event.target.value)
                            }
                        />
                        {error('release_details')}
                    </label>
                    <label className="block text-sm font-medium">
                        Autorité compétente *
                        <input
                            required
                            maxLength={255}
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm font-normal"
                            value={form.data.release_authority}
                            onChange={(event) =>
                                form.setData('release_authority', event.target.value)
                            }
                        />
                        {error('release_authority')}
                    </label>
                    <label className="block text-sm font-medium">
                        Référence de la décision *
                        <input
                            required
                            maxLength={255}
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm font-normal"
                            value={form.data.release_authority_reference}
                            onChange={(event) =>
                                form.setData(
                                    'release_authority_reference',
                                    event.target.value,
                                )
                            }
                        />
                        {error('release_authority_reference')}
                    </label>
                    <label className="block text-sm font-medium">
                        Date et heure *
                        <input
                            required
                            type="datetime-local"
                            step={1}
                            min={minReleaseDate}
                            max={formatDateTimeLocal(new Date())}
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm font-normal"
                            value={form.data.released_at}
                            onChange={(event) =>
                                form.setData('released_at', event.target.value)
                            }
                        />
                        {error('released_at')}
                    </label>
                    <DialogFooter>
                        <Button disabled={form.processing}>
                            Confirmer la libération
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Annuler
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function DetaineesIndex({
    detainees,
    canCreate,
    search,
    status,
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
                    {
                        status,
                        ...(query.trim() ? { search: query.trim() } : {}),
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            250,
        );
        return () => window.clearTimeout(timer);
    }, [query, status]);
    return (
        <>
            <Head title="Personnes détenues" />
            <div className="flex flex-col space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Personnes détenues"
                        description={
                            status === 'active'
                                ? 'Dossiers actifs accessibles selon votre affectation.'
                                : 'Dossiers des personnes libérées. Leur historique reste consultable dans chaque fiche.'
                        }
                    />
                    {canCreate ? (
                        <Button asChild>
                            <Link href="/detainees/create">
                                Enregistrer une admission
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <div className="flex gap-2 border-b">
                    <Link
                        href={`/detainees?status=active${search ? `&search=${encodeURIComponent(search)}` : ''}`}
                        className={`border-b-2 px-3 py-2 text-sm ${status === 'active' ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                    >
                        En détention
                    </Link>
                    <Link
                        href={`/detainees?status=released${search ? `&search=${encodeURIComponent(search)}` : ''}`}
                        className={`border-b-2 px-3 py-2 text-sm ${status === 'released' ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                    >
                        Libérés
                    </Link>
                </div>
                {status === 'released' ? (
                    <p className="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
                        Les dossiers libérés sont conservés. Une libération
                        clôt le séjour et l’affectation à la cellule; elle ne
                        supprime pas la fiche ni son historique.
                    </p>
                ) : null}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <input
                        aria-label="Rechercher une personne détenue"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Rechercher par numéro de dossier ou nom"
                        className="w-full max-w-lg rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <span className="text-sm text-muted-foreground">
                        {detainees.total} dossier(s) {status === 'active' ? 'actif(s)' : 'libéré(s)'}
                    </span>
                </div>
                {detainees.data.length === 0 ? (
                    <p className="rounded-lg border p-6 text-center text-muted-foreground">
                        {status === 'active'
                            ? 'Aucun dossier actif ne correspond à la recherche.'
                            : 'Aucun dossier de personne libérée ne correspond à la recherche.'}
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
                                                {status === 'active'
                                                    ? person.current_stay?.establishment.name ?? '—'
                                                    : person.latest_stay?.establishment.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {status === 'released'
                                                    ? person.latest_stay?.released_at
                                                        ? `Libéré le ${new Date(person.latest_stay.released_at).toLocaleDateString('fr-FR')}`
                                                        : '—'
                                                    : assignment
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
                                                    {status === 'active' ? (
                                                        <ReleaseForm id={person.id} admittedAt={person.current_stay.admitted_at} />
                                                    ) : canCreate ? (
                                                        <Button asChild size="sm">
                                                            <Link href={`/detainees/create?detainee_id=${person.id}`}>
                                                                Réadmettre
                                                            </Link>
                                                        </Button>
                                                    ) : null}
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
