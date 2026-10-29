import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ClipboardList, Search, UserRoundPlus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Establishment = { id: number; code: string; name: string };
type Detainee = {
    id: number;
    family_name: string;
    post_name: string | null;
    given_names: string;
};
type Visitor = {
    id: number;
    family_name: string;
    post_name: string | null;
    given_names: string;
    phone: string | null;
};
type Visit = {
    id: number;
    visit_type: string;
    relationship: string | null;
    status: string;
    scheduled_at: string | null;
    arrived_at: string | null;
    ended_at: string | null;
    cancellation_reason: string | null;
    detainee: Detainee;
    visitor: Visitor;
};
type Props = {
    establishments: Establishment[];
    selectedEstablishmentId: number;
    selectedDate: string;
    filters: { search: string; status: string };
    visits: {
        data: Visit[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    success: string | null;
};
const visitTypes: Record<string, string> = {
    family: 'Familiale',
    legal: 'Juridique',
    official: 'Officielle',
    other: 'Autre',
};
const visitStatuses: Record<string, string> = {
    scheduled: 'Programmée',
    arrived: 'Arrivée',
    completed: 'Terminée',
    cancelled: 'Annulée',
};

export default function VisitsIndex({
    establishments,
    selectedEstablishmentId,
    selectedDate,
    filters,
    visits,
    success,
}: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    function changeStatus(
        visit: Visit,
        status: 'arrived' | 'completed' | 'cancelled',
    ) {
        router.patch(`/visits/${visit.id}/status`, {
            status,
            ...(status === 'cancelled'
                ? { cancellation_reason: 'Annulée par le service des visites.' }
                : {}),
        });
    }
    return (
        <>
            <Head title="Visites" />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Gestion des visites"
                        description="Enregistrez les arrivées puis marquez chaque visite terminée à la sortie."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={`/visitors?establishment_id=${selectedEstablishmentId}`}
                            >
                                <UserRoundPlus size={16} className="mr-2" />
                                Visiteurs
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/visits/create?establishment_id=${selectedEstablishmentId}`}
                            >
                                <ClipboardList size={16} className="mr-2" />
                                Enregistrer une arrivée
                            </Link>
                        </Button>
                    </div>
                </div>
                {success ? (
                    <p className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                        {success}
                    </p>
                ) : null}
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            '/visits',
                            {
                                establishment_id: selectedEstablishmentId,
                                date: selectedDate,
                                search: searchTerm,
                                status,
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                    className="grid gap-4 rounded-lg border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <label
                        htmlFor="visits-establishment"
                        className="text-sm font-medium"
                    >
                        Établissement
                        <select
                            id="visits-establishment"
                            value={selectedEstablishmentId}
                            onChange={(event) =>
                                router.get('/visits', {
                                    establishment_id: Number(
                                        event.target.value,
                                    ),
                                    date: selectedDate,
                                    search: searchTerm,
                                    status,
                                })
                            }
                            className="mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {establishments.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.code} — {item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label
                        htmlFor="visits-date"
                        className="text-sm font-medium"
                    >
                        Date du journal
                        <input
                            id="visits-date"
                            type="date"
                            value={selectedDate}
                            onChange={(event) =>
                                router.get('/visits', {
                                    establishment_id: selectedEstablishmentId,
                                    date: event.target.value,
                                    search: searchTerm,
                                    status,
                                })
                            }
                            className="mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </label>
                    <label
                        htmlFor="visits-search"
                        className="text-sm font-medium lg:col-span-2"
                    >
                        Rechercher une personne
                        <input
                            id="visits-search"
                            type="search"
                            value={searchTerm}
                            maxLength={100}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            placeholder="Nom, téléphone ou numéro du détenu"
                            className="mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </label>
                    <label
                        htmlFor="visits-status"
                        className="text-sm font-medium"
                    >
                        Statut
                        <select
                            id="visits-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="arrived">Encore présent(e)</option>
                            <option value="scheduled">Programmée</option>
                            <option value="completed">Terminée</option>
                            <option value="cancelled">Annulée</option>
                            <option value="all">Toutes les visites</option>
                        </select>
                    </label>
                    <div className="sm:col-span-2 lg:col-span-5">
                        <Button type="submit" variant="outline">
                            <Search size={16} className="mr-2" />
                            Rechercher
                        </Button>
                    </div>
                </form>
                <section className="space-y-3">
                    <div>
                        <Heading
                            variant="small"
                            title={
                                status === 'arrived'
                                    ? 'Visiteurs encore présents'
                                    : 'Journal des visites'
                            }
                            description="Suivez les arrivées et terminez les visites à la sortie."
                        />
                        <p className="text-sm text-muted-foreground">
                            {visits.total} résultat(s) le{' '}
                            {new Date(
                                `${selectedDate}T12:00:00`,
                            ).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                    {visits.data.length ? (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[850px] text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Arrivée / sortie
                                        </th>
                                        <th className="px-4 py-3">
                                            Personne détenue
                                        </th>
                                        <th className="px-4 py-3">Visiteur</th>
                                        <th className="px-4 py-3">
                                            Type / lien
                                        </th>
                                        <th className="px-4 py-3">État</th>
                                        <th className="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visits.data.map((visit) => (
                                        <tr key={visit.id} className="border-t">
                                            <td className="px-4 py-3">
                                                <div>
                                                    Arrivée :{' '}
                                                    {visit.arrived_at
                                                        ? new Date(
                                                              visit.arrived_at,
                                                          ).toLocaleString(
                                                              'fr-FR',
                                                          )
                                                        : visit.scheduled_at
                                                          ? new Date(
                                                                visit.scheduled_at,
                                                            ).toLocaleString(
                                                                'fr-FR',
                                                            )
                                                          : '—'}
                                                </div>
                                                {visit.ended_at ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        Sortie :{' '}
                                                        {new Date(
                                                            visit.ended_at,
                                                        ).toLocaleString(
                                                            'fr-FR',
                                                        )}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                {visit.detainee.family_name}{' '}
                                                {visit.detainee.post_name ?? ''}{' '}
                                                {visit.detainee.given_names}
                                            </td>
                                            <td className="px-4 py-3">
                                                {visit.visitor.family_name}{' '}
                                                {visit.visitor.post_name ?? ''}{' '}
                                                {visit.visitor.given_names}
                                                <div className="text-xs text-muted-foreground">
                                                    {visit.visitor.phone ?? ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {visitTypes[visit.visit_type] ??
                                                    visit.visit_type}
                                                {visit.relationship
                                                    ? ` · ${visit.relationship}`
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-3">
                                                {visitStatuses[visit.status] ??
                                                    visit.status}
                                                {visit.cancellation_reason ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            visit.cancellation_reason
                                                        }
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {visit.status ===
                                                    'scheduled' ? (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        visit,
                                                                        'arrived',
                                                                    )
                                                                }
                                                            >
                                                                Arrivée
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        visit,
                                                                        'cancelled',
                                                                    )
                                                                }
                                                            >
                                                                Annuler
                                                            </Button>
                                                        </>
                                                    ) : null}
                                                    {visit.status ===
                                                    'arrived' ? (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        visit,
                                                                        'completed',
                                                                    )
                                                                }
                                                            >
                                                                Enregistrer la
                                                                sortie
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        visit,
                                                                        'cancelled',
                                                                    )
                                                                }
                                                            >
                                                                Annuler
                                                            </Button>
                                                        </>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed p-8 text-center">
                            <p className="text-sm text-muted-foreground">
                                Aucune visite ne correspond à ces filtres.
                                Essayez un autre nom, un autre statut ou une
                                autre date.
                            </p>
                            <Button className="mt-4" asChild>
                                <Link
                                    href={`/visits/create?establishment_id=${selectedEstablishmentId}`}
                                >
                                    Enregistrer la première arrivée
                                </Link>
                            </Button>
                        </div>
                    )}
                </section>
                <nav
                    aria-label="Pages du journal quotidien"
                    className="flex flex-wrap gap-1"
                >
                    {visits.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-accent'}`}
                            >
                                {link.label
                                    .replace('&laquo; ', '')
                                    .replace(' &raquo;', '')
                                    .replace('Previous', 'Précédent')
                                    .replace('Next', 'Suivant')}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="rounded border px-3 py-1.5 text-sm text-muted-foreground"
                            >
                                {link.label
                                    .replace('&laquo; ', '')
                                    .replace(' &raquo;', '')
                                    .replace('Previous', 'Précédent')
                                    .replace('Next', 'Suivant')}
                            </span>
                        ),
                    )}
                </nav>
            </main>
        </>
    );
}
VisitsIndex.layout = { breadcrumbs: [{ title: 'Visites', href: '/visits' }] };
