import { FormEvent, MouseEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Siren } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Establishment = { id: number; code: string; name: string };
type Incident = {
    id: number;
    incident_type: string;
    severity: string;
    occurred_at: string;
    location: string | null;
    description: string;
    immediate_action: string | null;
    status: string;
    reporter: { id: number; name: string } | null;
};
type Props = {
    establishments: Establishment[];
    selectedEstablishmentId: number;
    incidents: {
        data: Incident[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    success: string | null;
};
const types: Record<string, string> = {
    violence: 'Violence',
    medical: 'Urgence médicale',
    escape_attempt: 'Tentative d’évasion',
    damage: 'Dégradation',
    fire: 'Incendie',
    security: 'Sécurité',
    other: 'Autre',
};
const severities: Record<string, string> = {
    low: 'Faible',
    medium: 'Modérée',
    high: 'Élevée',
    critical: 'Critique',
};
const statuses: Record<string, string> = {
    open: 'À traiter',
    under_review: 'En cours de suivi',
    resolved: 'Clôturé',
};

export default function IncidentsIndex({
    establishments,
    selectedEstablishmentId,
    incidents,
    success,
}: Props) {
    const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
    const form = useForm({
        establishment_id: selectedEstablishmentId,
        incident_type: 'other',
        severity: 'medium',
        occurred_at: now,
        location: '',
        description: '',
        immediate_action: '',
    });
    const followUp = useForm({
        status: 'under_review',
        action_description: '',
    });
    function report(event: FormEvent) {
        event.preventDefault();
        form.post('/incidents', {
            onSuccess: () =>
                form.reset(
                    'incident_type',
                    'severity',
                    'occurred_at',
                    'location',
                    'description',
                    'immediate_action',
                ),
        });
    }
    function saveFollowUp(
        event: MouseEvent<HTMLButtonElement>,
        incident: Incident,
        status: 'under_review' | 'resolved',
    ) {
        event.preventDefault();
        followUp.transform((data) => ({ ...data, status }));
        followUp.patch(`/incidents/${incident.id}/status`, {
            onSuccess: () => followUp.reset('action_description'),
        });
    }

    return (
        <>
            <Head title="Incidents" />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Incidents et événements"
                        description="Signaler un événement, consigner les premières mesures et suivre son traitement."
                    />
                    <Button variant="outline" asChild>
                        <Link href="/dashboard">Tableau de bord</Link>
                    </Button>
                </div>
                {success ? (
                    <p className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                        {success}
                    </p>
                ) : null}
                <div className="rounded-lg border bg-card p-4">
                    <label
                        htmlFor="incident-establishment"
                        className="text-sm font-medium"
                    >
                        Établissement
                    </label>
                    <select
                        id="incident-establishment"
                        value={selectedEstablishmentId}
                        onChange={(event) =>
                            router.get('/incidents', {
                                establishment_id: Number(event.target.value),
                            })
                        }
                        className="mt-2 w-full max-w-xl rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        {establishments.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.code} — {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                <form
                    onSubmit={report}
                    className="space-y-4 rounded-lg border bg-card p-5"
                >
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Siren size={18} /> Signaler un incident
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label className="text-sm">
                            Type
                            <select
                                value={form.data.incident_type}
                                onChange={(event) =>
                                    form.setData(
                                        'incident_type',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            >
                                {Object.entries(types).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.incident_type && (
                                <small className="text-destructive">
                                    {form.errors.incident_type}
                                </small>
                            )}
                        </label>
                        <label className="text-sm">
                            Gravité
                            <select
                                value={form.data.severity}
                                onChange={(event) =>
                                    form.setData('severity', event.target.value)
                                }
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            >
                                {Object.entries(severities).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            {form.errors.severity && (
                                <small className="text-destructive">
                                    {form.errors.severity}
                                </small>
                            )}
                        </label>
                        <label className="text-sm">
                            Date et heure
                            <input
                                required
                                type="datetime-local"
                                max={now}
                                value={form.data.occurred_at}
                                onChange={(event) =>
                                    form.setData(
                                        'occurred_at',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            />
                            {form.errors.occurred_at && (
                                <small className="text-destructive">
                                    {form.errors.occurred_at}
                                </small>
                            )}
                        </label>
                        <label className="text-sm sm:col-span-2 lg:col-span-3">
                            Lieu
                            <input
                                maxLength={255}
                                value={form.data.location}
                                onChange={(event) =>
                                    form.setData('location', event.target.value)
                                }
                                placeholder="Bâtiment, cellule ou autre lieu"
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            />
                            {form.errors.location && (
                                <small className="text-destructive">
                                    {form.errors.location}
                                </small>
                            )}
                        </label>
                        <label className="text-sm sm:col-span-2 lg:col-span-3">
                            Description factuelle
                            <textarea
                                required
                                maxLength={10000}
                                rows={4}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            />
                            {form.errors.description && (
                                <small className="text-destructive">
                                    {form.errors.description}
                                </small>
                            )}
                        </label>
                        <label className="text-sm sm:col-span-2 lg:col-span-3">
                            Mesures immédiates prises
                            <textarea
                                maxLength={5000}
                                rows={3}
                                value={form.data.immediate_action}
                                onChange={(event) =>
                                    form.setData(
                                        'immediate_action',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                            />
                            {form.errors.immediate_action && (
                                <small className="text-destructive">
                                    {form.errors.immediate_action}
                                </small>
                            )}
                        </label>
                    </div>
                    <Button disabled={form.processing}>
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Enregistrer le signalement'}
                    </Button>
                </form>
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Registre des incidents"
                            description="Les descriptions et mesures sont stockées chiffrées. Seuls les agents autorisés de l’établissement et l’administration centrale y ont accès."
                        />
                        <span className="text-sm text-muted-foreground">
                            {incidents.total} incident(s)
                        </span>
                    </div>
                    {incidents.data.length ? (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Date / lieu
                                        </th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">Gravité</th>
                                        <th className="px-4 py-3">
                                            Description / mesures immédiates
                                        </th>
                                        <th className="px-4 py-3">
                                            État / signalé par
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {incidents.data.map((incident) => (
                                        <tr
                                            key={incident.id}
                                            className="border-t align-top"
                                        >
                                            <td className="px-4 py-3">
                                                {new Date(
                                                    incident.occurred_at,
                                                ).toLocaleString('fr-FR')}
                                                <div className="text-xs text-muted-foreground">
                                                    {incident.location ||
                                                        'Lieu non précisé'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {types[
                                                    incident.incident_type
                                                ] ?? incident.incident_type}
                                            </td>
                                            <td className="px-4 py-3">
                                                {severities[
                                                    incident.severity
                                                ] ?? incident.severity}
                                            </td>
                                            <td className="max-w-md px-4 py-3 whitespace-pre-wrap">
                                                {incident.description}
                                                {incident.immediate_action ? (
                                                    <div className="mt-2 border-l-2 pl-2 text-xs text-muted-foreground">
                                                        Mesures :{' '}
                                                        {
                                                            incident.immediate_action
                                                        }
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                {statuses[incident.status] ??
                                                    incident.status}
                                                <div className="text-xs text-muted-foreground">
                                                    {incident.reporter?.name ??
                                                        'Compte supprimé'}
                                                </div>
                                                {incident.status !==
                                                'resolved' ? (
                                                    <form className="mt-3 space-y-2">
                                                        <textarea
                                                            required
                                                            maxLength={5000}
                                                            rows={2}
                                                            value={
                                                                followUp.data
                                                                    .action_description
                                                            }
                                                            onChange={(event) =>
                                                                followUp.setData(
                                                                    'action_description',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Décrire le suivi ou la clôture"
                                                            className="w-56 rounded-md border bg-background px-2 py-1 text-xs"
                                                        />
                                                        <div className="flex flex-wrap gap-1">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    followUp.processing
                                                                }
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    saveFollowUp(
                                                                        event,
                                                                        incident,
                                                                        'under_review',
                                                                    )
                                                                }
                                                            >
                                                                Suivre
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                disabled={
                                                                    followUp.processing
                                                                }
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    saveFollowUp(
                                                                        event,
                                                                        incident,
                                                                        'resolved',
                                                                    )
                                                                }
                                                            >
                                                                Clôturer
                                                            </Button>
                                                        </div>
                                                    </form>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                            Aucun incident n’a encore été signalé pour cet
                            établissement.
                        </p>
                    )}
                    <nav
                        aria-label="Pages des incidents"
                        className="flex flex-wrap gap-1"
                    >
                        {incidents.links.map((link, index) =>
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
                </section>
            </main>
        </>
    );
}

IncidentsIndex.layout = {
    breadcrumbs: [{ title: 'Incidents', href: '/incidents' }],
};
