import { FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Package, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { SearchableSelect, SearchOption } from '@/components/searchable-select';

type Establishment = { id: number; code: string; name: string };
type Item = {
    id: number;
    code: string;
    name: string;
    category: string;
    unit: string;
};
type Stock = {
    id: number;
    location: string;
    quantity: string;
    reorder_threshold: string;
    item: Item;
};
type Movement = {
    id: number;
    movement_type: 'entry' | 'issue';
    quantity: string;
    source_location: string | null;
    destination_location: string | null;
    reference: string | null;
    reason: string | null;
    created_at: string;
    item: Item;
    recorder: { name: string } | null;
};
type Props = {
    establishments: Establishment[];
    selectedEstablishmentId: number;
    hasItems: boolean;
    stocks: Stock[];
    movements: {
        data: Movement[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    canManageCatalog: boolean;
    success: string | null;
};

export default function StocksIndex({
    establishments,
    selectedEstablishmentId,
    hasItems,
    stocks,
    movements,
    canManageCatalog,
    success,
}: Props) {
    const [selectedItem, setSelectedItem] = useState<SearchOption | null>(null);
    const itemForm = useForm({
        code: '',
        name: '',
        category: '',
        unit: '',
        description: '',
    });
    const movementForm = useForm<{
        inventory_item_id: number | '';
        movement_type: 'entry' | 'issue';
        quantity: string;
        location: string;
        reorder_threshold: string;
        reference: string;
        reason: string;
    }>({
        inventory_item_id: '',
        movement_type: 'entry',
        quantity: '',
        location: 'Magasin principal',
        reorder_threshold: '0',
        reference: '',
        reason: '',
    });
    useEffect(() => {
        setSelectedItem(null);
        movementForm.setData('inventory_item_id', '');
    }, [selectedEstablishmentId]);
    function submitItem(event: FormEvent) {
        event.preventDefault();
        itemForm.post('/stocks/items', { onSuccess: () => itemForm.reset() });
    }
    function submitMovement(event: FormEvent) {
        event.preventDefault();
        movementForm.post(
            `/establishments/${selectedEstablishmentId}/stock-movements`,
            {
                onSuccess: () =>
                    movementForm.reset('quantity', 'reference', 'reason'),
            },
        );
    }
    return (
        <>
            <Head title="Stocks" />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Gestion des stocks"
                        description="Consultez les quantités et consignez chaque entrée ou sortie."
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
                        htmlFor="establishment"
                        className="text-sm font-medium"
                    >
                        Établissement
                    </label>
                    <select
                        id="establishment"
                        value={selectedEstablishmentId}
                        onChange={(event) =>
                            router.get('/stocks', {
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
                <section className="grid gap-5 xl:grid-cols-2">
                    {canManageCatalog ? (
                        <form
                            onSubmit={submitItem}
                            className="space-y-4 rounded-lg border bg-card p-5"
                        >
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Plus size={18} /> Ajouter un article au
                                catalogue commun
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="text-sm">
                                    Code
                                    <input
                                        required
                                        value={itemForm.data.code}
                                        onChange={(e) =>
                                            itemForm.setData(
                                                'code',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                    />
                                    {itemForm.errors.code && (
                                        <small className="text-destructive">
                                            {itemForm.errors.code}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm">
                                    Désignation
                                    <input
                                        required
                                        value={itemForm.data.name}
                                        onChange={(e) =>
                                            itemForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                    />
                                    {itemForm.errors.name && (
                                        <small className="text-destructive">
                                            {itemForm.errors.name}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm">
                                    Catégorie
                                    <input
                                        required
                                        value={itemForm.data.category}
                                        onChange={(e) =>
                                            itemForm.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Alimentation, hygiène…"
                                        className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                    />
                                </label>
                                <label className="text-sm">
                                    Unité
                                    <input
                                        required
                                        value={itemForm.data.unit}
                                        onChange={(e) =>
                                            itemForm.setData(
                                                'unit',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="kg, pièce, litre…"
                                        className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                    />
                                </label>
                            </div>
                            <Button disabled={itemForm.processing}>
                                Enregistrer l’article
                            </Button>
                        </form>
                    ) : (
                        <div className="rounded-lg border bg-card p-5">
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Package size={18} /> Catalogue
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Le catalogue est géré par l’administrateur
                                central. Les agents autorisés peuvent ensuite
                                enregistrer les mouvements de stock.
                            </p>
                        </div>
                    )}
                    <form
                        onSubmit={submitMovement}
                        className="space-y-4 rounded-lg border bg-card p-5"
                    >
                        <h2 className="font-semibold">
                            Enregistrer un mouvement
                        </h2>
                        {hasItems ? (
                            <>
                                <SearchableSelect
                                    label="Article"
                                    placeholder="Code, désignation ou catégorie"
                                    searchUrl={`/establishments/${selectedEstablishmentId}/stock-items`}
                                    selected={selectedItem}
                                    onSelect={(option) => {
                                        setSelectedItem(option);
                                        movementForm.setData(
                                            'inventory_item_id',
                                            option?.id ?? '',
                                        );
                                    }}
                                    error={
                                        movementForm.errors.inventory_item_id
                                    }
                                />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <label className="text-sm">
                                        Type
                                        <select
                                            value={
                                                movementForm.data.movement_type
                                            }
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'movement_type',
                                                    e.target.value as
                                                        | 'entry'
                                                        | 'issue',
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        >
                                            <option value="entry">
                                                Entrée
                                            </option>
                                            <option value="issue">
                                                Sortie
                                            </option>
                                        </select>
                                    </label>
                                    <label className="text-sm">
                                        Quantité
                                        <input
                                            required
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            value={movementForm.data.quantity}
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                        {movementForm.errors.quantity && (
                                            <small className="text-destructive">
                                                {movementForm.errors.quantity}
                                            </small>
                                        )}
                                    </label>
                                    <label className="text-sm">
                                        Emplacement
                                        <input
                                            required
                                            value={movementForm.data.location}
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'location',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Seuil de réapprovisionnement
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            value={
                                                movementForm.data
                                                    .reorder_threshold
                                            }
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'reorder_threshold',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Référence
                                        <input
                                            value={movementForm.data.reference}
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'reference',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        Motif
                                        <input
                                            value={movementForm.data.reason}
                                            onChange={(e) =>
                                                movementForm.setData(
                                                    'reason',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                    </label>
                                </div>
                                {movementForm.errors.inventory_item_id && (
                                    <p className="text-sm text-destructive">
                                        {movementForm.errors.inventory_item_id}
                                    </p>
                                )}
                                <Button disabled={movementForm.processing}>
                                    {movementForm.data.movement_type ===
                                    'entry' ? (
                                        <ArrowDownToLine size={16} />
                                    ) : (
                                        <ArrowUpFromLine size={16} />
                                    )}
                                    {movementForm.processing
                                        ? 'Enregistrement…'
                                        : 'Valider le mouvement'}
                                </Button>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Aucun article actif dans le catalogue.
                                L’administrateur central doit d’abord en
                                ajouter.
                            </p>
                        )}
                    </form>
                </section>
                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="État du stock"
                        description="Les alertes apparaissent lorsque la quantité atteint le seuil défini."
                    />
                    {stocks.length ? (
                        <div className="overflow-x-auto rounded-lg border bg-card">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">Article</th>
                                        <th className="px-4 py-3">Catégorie</th>
                                        <th className="px-4 py-3">
                                            Emplacement
                                        </th>
                                        <th className="px-4 py-3">Quantité</th>
                                        <th className="px-4 py-3">Seuil</th>
                                        <th className="px-4 py-3">État</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stocks.map((stock) => {
                                        const low =
                                            Number(stock.quantity) <=
                                            Number(stock.reorder_threshold);
                                        return (
                                            <tr
                                                key={stock.id}
                                                className="border-t"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {stock.item.code} —{' '}
                                                    {stock.item.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {stock.item.category}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {stock.location}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {Number(
                                                        stock.quantity,
                                                    ).toLocaleString(
                                                        'fr-FR',
                                                    )}{' '}
                                                    {stock.item.unit}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {Number(
                                                        stock.reorder_threshold,
                                                    ).toLocaleString(
                                                        'fr-FR',
                                                    )}{' '}
                                                    {stock.item.unit}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={
                                                            low
                                                                ? 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900'
                                                                : 'rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-900'
                                                        }
                                                    >
                                                        {low
                                                            ? 'À réapprovisionner'
                                                            : 'Disponible'}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                            Aucun stock enregistré. Enregistrez une première
                            entrée pour afficher l’article ici.
                        </p>
                    )}
                </section>
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Historique des mouvements"
                            description="Entrées et sorties enregistrées pour cet établissement."
                        />
                        <span className="text-sm text-muted-foreground">
                            {movements.total} mouvement(s)
                        </span>
                    </div>
                    {movements.data.length ? (
                        <div className="overflow-x-auto rounded-lg border bg-card">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">Article</th>
                                        <th className="px-4 py-3">Mouvement</th>
                                        <th className="px-4 py-3">Quantité</th>
                                        <th className="px-4 py-3">
                                            Emplacement
                                        </th>
                                        <th className="px-4 py-3">
                                            Référence / motif
                                        </th>
                                        <th className="px-4 py-3">
                                            Enregistré par
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {movements.data.map((m) => (
                                        <tr key={m.id} className="border-t">
                                            <td className="px-4 py-3">
                                                {new Date(
                                                    m.created_at,
                                                ).toLocaleString('fr-FR')}
                                            </td>
                                            <td className="px-4 py-3">
                                                {m.item.code} — {m.item.name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {m.movement_type === 'entry' ? (
                                                    <span className="text-green-700">
                                                        Entrée
                                                    </span>
                                                ) : (
                                                    <span className="text-amber-800">
                                                        Sortie
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {Number(
                                                    m.quantity,
                                                ).toLocaleString('fr-FR')}{' '}
                                                {m.item.unit}
                                            </td>
                                            <td className="px-4 py-3">
                                                {m.destination_location ??
                                                    m.source_location ??
                                                    '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {[m.reference, m.reason]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {m.recorder?.name ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                            Aucun mouvement enregistré.
                        </p>
                    )}
                    <nav
                        aria-label="Pages des mouvements de stock"
                        className="flex flex-wrap gap-1"
                    >
                        {movements.links.map((link, index) =>
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
StocksIndex.layout = { breadcrumbs: [{ title: 'Stocks', href: '/stocks' }] };
