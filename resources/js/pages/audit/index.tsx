import { FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Download, ScrollText } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type AuditLog = {
    metadata: {
        cell_code?: string;
        capacity?: number;
        occupied?: number;
        excess?: number;
        ip?: string | null;
        user_agent?: string | null;
        login_identifier?: string | null;
    } | null;
    id: number;
    event: string;
    subject_type: string;
    subject_id: number | null;
    summary: string;
    created_at: string;
    actor: { id: number; name: string } | null;
    establishment: { id: number; code: string; name: string } | null;
};
type EventKey =
    | 'auth.login'
    | 'auth.logout'
    | 'auth.failed'
    | 'detainee.admitted'
    | 'detainee.transferred'
    | 'detainee.cell_moved'
    | 'cell.overcrowding'
    | 'cell.overcrowding_resolved'
    | 'detainee.released'
    | 'incident.reported'
    | 'incident.updated'
    | 'visitor.created'
    | 'visit.scheduled'
    | 'visit.arrived'
    | 'visit.completed'
    | 'visit.cancelled'
    | 'stock.movement'
    | 'medical.consultation';
type Props = {
    logs: {
        data: AuditLog[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    establishments: { id: number; code: string; name: string }[];
    filters: {
        event: string;
        establishment_id: string | number;
        from: string;
        to: string;
    };
};
const events: Record<EventKey, string> = {
    'auth.login': 'Connexion réussie',
    'auth.logout': 'Déconnexion',
    'auth.failed': 'Échec de connexion',
    'detainee.admitted': 'Admission',
    'detainee.transferred': 'Transfert',
    'detainee.cell_moved': 'Changement de cellule',
    'cell.overcrowding': 'Surpopulation constatée',
    'cell.overcrowding_resolved': 'Surpopulation résorbée',
    'detainee.released': 'Libération',
    'incident.reported': 'Signalement d’incident',
    'incident.updated': 'Mise à jour d’incident',
    'visitor.created': 'Enregistrement d’un visiteur',
    'visit.scheduled': 'Programmation d’une visite',
    'visit.arrived': 'Arrivée du visiteur',
    'visit.completed': 'Visite terminée',
    'visit.cancelled': 'Visite annulée',
    'stock.movement': 'Mouvement de stock',
    'medical.consultation': 'Consultation médicale',
};
const subjects: Record<string, string> = {
    user: 'Utilisateur',
    cell: 'Cellule',
    cell_assignment: 'Affectation de cellule',
    detainee: 'Dossier détenu',
    incident: 'Incident',
    visitor: 'Visiteur',
    visit: 'Visite',
    stock_movement: 'Mouvement de stock',
    medical_consultation: 'Consultation médicale',
};
const pageLabel = (label: string) =>
    label
        .replace(/&laquo;|&raquo;/g, '')
        .replace('Previous', 'Précédent')
        .replace('Next', 'Suivant')
        .trim();

export default function AuditIndex({
    logs,
    establishments,
    filters: initialFilters,
}: Props) {
    const [filters, setFilters] = useState(initialFilters);
    const exportParams = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '') exportParams.set(key, String(value));
    });
    const exportQuery = exportParams.toString();
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get('/audit-log', filters, {
            preserveState: true,
            replace: true,
        });
    }
    function reset() {
        const empty = { event: '', establishment_id: '', from: '', to: '' };
        setFilters(empty);
        router.get('/audit-log', {}, { replace: true });
    }
    return (
        <>
            <Head title="Journal d’audit" />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <ScrollText className="size-6 text-primary" />
                        <Heading
                            variant="small"
                            title="Journal d’audit"
                            description="Registre chronologique des actions sensibles et des connexions à SIGEC."
                        />
                    </div>
                    <Button variant="outline" asChild>
                        <a
                            href={`/audit-log/export.csv${exportQuery ? `?${exportQuery}` : ''}`}
                        >
                            <Download size={16} /> Exporter les résultats CSV
                        </a>
                    </Button>
                </div>
                <p className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    Les entrées sont en lecture seule. Les mots de passe ne sont
                    jamais enregistrés. Les tentatives échouées conservent
                    uniquement l’identifiant de connexion saisi, l’adresse IP et
                    le navigateur utilisé.
                </p>
                <form
                    onSubmit={submit}
                    className="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 xl:grid-cols-5"
                >
                    <label className="text-sm">
                        Établissement
                        <select
                            value={filters.establishment_id}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    establishment_id: event.target.value,
                                })
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        >
                            <option value="">Tous</option>
                            {establishments.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.code} — {item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        Action
                        <select
                            value={filters.event}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    event: event.target.value,
                                })
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        >
                            <option value="">Toutes</option>
                            {Object.entries(events).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        Du
                        <input
                            type="date"
                            value={filters.from}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    from: event.target.value,
                                })
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                    </label>
                    <label className="text-sm">
                        Au
                        <input
                            type="date"
                            value={filters.to}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    to: event.target.value,
                                })
                            }
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                        />
                    </label>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Filtrer</Button>
                        <Button type="button" variant="outline" onClick={reset}>
                            Réinitialiser
                        </Button>
                    </div>
                </form>
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Actions récentes</h2>
                        <span className="text-sm text-muted-foreground">
                            {logs.total} entrée(s)
                        </span>
                    </div>
                    {logs.data.length ? (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Date et heure
                                        </th>
                                        <th className="px-4 py-3">Action</th>
                                        <th className="px-4 py-3">
                                            Établissement
                                        </th>
                                        <th className="px-4 py-3">Objet</th>
                                        <th className="px-4 py-3">
                                            Agent / identifiant
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="border-t">
                                            <td className="px-4 py-3">
                                                {new Date(
                                                    log.created_at,
                                                ).toLocaleString('fr-FR')}
                                            </td>
                                            <td className="px-4 py-3">
                                                <p>
                                                    {events[
                                                        log.event as EventKey
                                                    ] ?? log.summary}
                                                </p>
                                                {log.metadata?.cell_code ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Cellule{' '}
                                                        {log.metadata.cell_code}{' '}
                                                        ·{' '}
                                                        {log.metadata
                                                            .occupied ??
                                                            '—'}{' '}
                                                        /{' '}
                                                        {log.metadata
                                                            .capacity ??
                                                            '—'}{' '}
                                                        place(s)
                                                        {log.metadata.excess
                                                            ? ` · dépassement +${log.metadata.excess}`
                                                            : ''}
                                                    </p>
                                                ) : null}
                                                {log.metadata?.ip ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        IP : {log.metadata.ip}
                                                    </p>
                                                ) : null}
                                                {log.metadata?.user_agent ? (
                                                    <p
                                                        className="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                                        title={
                                                            log.metadata
                                                                .user_agent
                                                        }
                                                    >
                                                        {
                                                            log.metadata
                                                                .user_agent
                                                        }
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                {log.establishment
                                                    ? `${log.establishment.code} — ${log.establishment.name}`
                                                    : 'Administration centrale'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {log.subject_type === 'cell' &&
                                                log.metadata?.cell_code
                                                    ? `${log.metadata.cell_code} · `
                                                    : ''}
                                                {subjects[log.subject_type] ??
                                                    log.subject_type}
                                                {log.subject_id
                                                    ? ` #${log.subject_id}`
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-3">
                                                {log.actor?.name ??
                                                    log.metadata
                                                        ?.login_identifier ??
                                                    (log.event === 'auth.failed'
                                                        ? 'Identifiant non trouvé'
                                                        : 'Compte supprimé')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                            Aucune action n’a encore été ajoutée au journal. Les
                            prochaines admissions, libérations, mouvements,
                            incidents et connexions y seront consignés.
                        </p>
                    )}
                </section>
                <nav
                    aria-label="Pages du journal"
                    className="flex flex-wrap gap-1"
                >
                    {logs.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-accent'}`}
                            >
                                {pageLabel(link.label)}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="rounded border px-3 py-1.5 text-sm text-muted-foreground"
                            >
                                {pageLabel(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            </main>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Journal d’audit', href: '/audit-log' }],
};
