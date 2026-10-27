import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Plus, Trash2 } from 'lucide-react';
import { SearchableSelect, SearchOption } from '@/components/searchable-select';

type Detainee = { id: number; record_number: string; family_name: string; post_name: string | null; given_names: string };
type Assignment = { id: number; detainee: Detainee };
type Cell = { id: number; code: string; name: string | null; capacity: number; cell_type: string | null; occupied: number; active_assignments: Assignment[] };
type Building = { id: number; code: string; name: string; building_type: string | null; cells: Cell[] };
type Props = { establishment: { id: number; code: string; name: string }; buildings: Building[]; hasUnassignedDetainees: boolean };

function MoveAssignment({ establishmentId, assignmentId }: { establishmentId: number; assignmentId: number }) {
    const form = useForm({ cell_id: '', reason: '' });
    const [cell, setCell] = useState<SearchOption | null>(null);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/establishments/${establishmentId}/cell-assignments/${assignmentId}/move`, { onSuccess: () => { form.reset(); setCell(null); } });
    }
    return <form onSubmit={submit} className="mt-2 grid max-w-2xl gap-2 rounded-md bg-muted/30 p-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <SearchableSelect label="Déplacer vers" placeholder="Cellule de destination" searchUrl={`/establishments/${establishmentId}/available-cells`} selected={cell} onSelect={option => { setCell(option); form.setData('cell_id', option?.id.toString() ?? ''); }} error={form.errors.cell_id} />
        <label className="text-xs">Motif du déplacement<input required maxLength={255} value={form.data.reason} onChange={event => form.setData('reason', event.target.value)} className="mt-1 w-full rounded-md border bg-background px-2 py-2 text-sm" />{form.errors.reason && <small className="text-destructive">{form.errors.reason}</small>}</label>
        <Button size="sm" disabled={form.processing || !cell}>Déplacer</Button>
    </form>;
}

export default function EstablishmentStructure({ establishment, buildings, hasUnassignedDetainees }: Props) {
    const buildingForm = useForm({ code: '', name: '', building_type: '', notes: '' });
    const cellForm = useForm<{ establishment_building_id: number | ''; cells: { code: string; name: string; capacity: number; cell_type: string; notes: string }[] }>({ establishment_building_id: buildings[0]?.id ?? '', cells: [{ code: '', name: '', capacity: 1, cell_type: '', notes: '' }] });
    const assignmentForm = useForm({ cell_id: '', detainee_id: '' });
    const [selectedAssignmentDetainee, setSelectedAssignmentDetainee] = useState<SearchOption | null>(null);
    const [selectedAssignmentCell, setSelectedAssignmentCell] = useState<SearchOption | null>(null);
    function submitBuilding(event: FormEvent) { event.preventDefault(); buildingForm.post(`/establishments/${establishment.id}/buildings`, { onSuccess: () => buildingForm.reset() }); }
    function renameBuilding(building: Building) {
        const name = window.prompt('Nouveau nom du bâtiment :', building.name);
        if (name === null || !name.trim() || name.trim() === building.name) return;
        router.patch(`/establishments/${establishment.id}/buildings/${building.id}`, { name: name.trim() }, { preserveScroll: true });
    }
    function renameCell(cell: Cell) {
        const name = window.prompt('Nouveau nom de la cellule :', cell.name ?? '');
        if (name === null || name.trim() === (cell.name ?? '')) return;
        router.patch(`/establishments/${establishment.id}/cells/${cell.id}`, { name: name.trim() || null }, { preserveScroll: true });
    }
    function submitCell(event: FormEvent) { event.preventDefault(); cellForm.post(`/establishments/${establishment.id}/cells`, { onSuccess: () => cellForm.setData('cells', [{ code: '', name: '', capacity: 1, cell_type: '', notes: '' }]) }); }
    function submitAssignment(event: FormEvent) { event.preventDefault(); assignmentForm.post(`/establishments/${establishment.id}/cell-assignments`, { onSuccess: () => { assignmentForm.reset(); setSelectedAssignmentDetainee(null); setSelectedAssignmentCell(null); } }); }
    return <>
        <Head title={`Bâtiments et cellules — ${establishment.name}`} />
        <div className="mx-auto flex max-w-6xl flex-col gap-6 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3"><Heading variant="small" title="Bâtiments et cellules" description={`${establishment.code} — ${establishment.name}`} /><Button variant="outline" asChild><Link href="/establishments">Retour aux établissements</Link></Button></div>
            <section className="grid gap-5 lg:grid-cols-2">
                <form onSubmit={submitBuilding} className="space-y-4 rounded-lg border p-5"><h2 className="font-semibold">Ajouter un bâtiment</h2>
                    <label className="block text-sm">Code<input required value={buildingForm.data.code} onChange={e => buildingForm.setData('code', e.target.value)} className="mt-1 w-full rounded-md border bg-background px-3 py-2" />{buildingForm.errors.code && <small className="text-destructive">{buildingForm.errors.code}</small>}</label>
                    <label className="block text-sm">Nom<input required value={buildingForm.data.name} onChange={e => buildingForm.setData('name', e.target.value)} className="mt-1 w-full rounded-md border bg-background px-3 py-2" /></label>
                    <label className="block text-sm">Type (facultatif)<input value={buildingForm.data.building_type} onChange={e => buildingForm.setData('building_type', e.target.value)} className="mt-1 w-full rounded-md border bg-background px-3 py-2" /></label>
                    <Button disabled={buildingForm.processing}>Ajouter le bâtiment</Button>
                </form>
                <form onSubmit={submitCell} className="space-y-4 rounded-lg border p-5"><div className="flex items-center justify-between gap-3"><h2 className="font-semibold">Ajouter des cellules</h2><Button type="button" size="sm" variant="outline" disabled={buildings.length === 0} onClick={() => cellForm.setData('cells', [...cellForm.data.cells, { code: '', name: '', capacity: 1, cell_type: '', notes: '' }])}><Plus className="mr-1 size-4" /> Ajouter une ligne</Button></div>
                    {buildings.length === 0 ? <p className="text-sm text-muted-foreground">Ajoutez d’abord un bâtiment.</p> : <>
                        <label className="block text-sm">Bâtiment<select required value={cellForm.data.establishment_building_id} onChange={e => cellForm.setData('establishment_building_id', Number(e.target.value))} className="mt-1 w-full rounded-md border bg-background px-3 py-2">{buildings.map(b => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select></label>
                        <div className="space-y-3">{cellForm.data.cells.map((cell, index) => <div key={index} className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_1fr_100px_auto]"><label className="block text-sm">Code<input required value={cell.code} onChange={e => cellForm.setData('cells', cellForm.data.cells.map((item, i) => i === index ? { ...item, code: e.target.value } : item))} className="mt-1 w-full rounded-md border bg-background px-3 py-2" />{cellForm.errors[`cells.${index}.code` as keyof typeof cellForm.errors] && <small className="text-destructive">{cellForm.errors[`cells.${index}.code` as keyof typeof cellForm.errors]}</small>}</label><label className="block text-sm">Nom (facultatif)<input value={cell.name} onChange={e => cellForm.setData('cells', cellForm.data.cells.map((item, i) => i === index ? { ...item, name: e.target.value } : item))} className="mt-1 w-full rounded-md border bg-background px-3 py-2" /></label><label className="block text-sm">Capacité<input required min={1} max={500} type="number" value={cell.capacity} onChange={e => cellForm.setData('cells', cellForm.data.cells.map((item, i) => i === index ? { ...item, capacity: Number(e.target.value) } : item))} className="mt-1 w-full rounded-md border bg-background px-3 py-2" />{cellForm.errors[`cells.${index}.capacity` as keyof typeof cellForm.errors] && <small className="text-destructive">{cellForm.errors[`cells.${index}.capacity` as keyof typeof cellForm.errors]}</small>}</label><div className="flex items-end"><Button type="button" variant="ghost" size="icon" aria-label="Retirer cette ligne" disabled={cellForm.data.cells.length === 1} onClick={() => cellForm.setData('cells', cellForm.data.cells.filter((_, i) => i !== index))}><Trash2 className="size-4" /></Button></div></div>)}</div>
                        <Button disabled={cellForm.processing}>{cellForm.processing ? 'Enregistrement…' : `Enregistrer ${cellForm.data.cells.length} cellule${cellForm.data.cells.length > 1 ? 's' : ''}`}</Button>
                    </>}
                </form>
            </section>
            <section className="space-y-4"><Heading variant="small" title="Occupation des cellules" description="Les places occupées sont calculées à partir des affectations en cours." />
                {buildings.length === 0 ? <p className="rounded-lg border p-6 text-center text-muted-foreground">Aucun bâtiment n’est encore enregistré.</p> : buildings.map(building => <div key={building.id} className="overflow-hidden rounded-lg border"><div className="border-b bg-muted/40 px-4 py-3 font-medium">{building.code} — {building.name}</div>{building.cells.length === 0 ? <p className="p-4 text-sm text-muted-foreground">Aucune cellule dans ce bâtiment.</p> : <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b text-muted-foreground"><th className="px-4 py-3">Cellule</th><th className="px-4 py-3">Occupation</th><th className="px-4 py-3">Personnes affectées</th></tr></thead><tbody>{building.cells.map(cell => <tr key={cell.id} className="border-b last:border-0"><td className="px-4 py-3 font-medium">{cell.code}{cell.name ? ` — ${cell.name}` : ''}</td><td className="px-4 py-3"><span>{cell.occupied} / {cell.capacity}</span>{cell.occupied > cell.capacity && <p className="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">Surpopulation : +{cell.occupied - cell.capacity}</p>}</td><td className="px-4 py-3"><div className="flex flex-col gap-3">{cell.active_assignments.length ? cell.active_assignments.map(a => <div key={a.id} className="rounded-md border p-2"><Link href={`/detainees/${a.detainee.id}`} className="font-medium text-primary hover:underline">{a.detainee.family_name} {a.detainee.post_name ?? ''} {a.detainee.given_names} · Ouvrir le dossier</Link><MoveAssignment establishmentId={establishment.id} assignmentId={a.id} /></div>) : 'Aucune'}</div></td></tr>)}</tbody></table></div>}</div>)}
            </section>
            <section className="space-y-3 rounded-lg border p-5">
                <Heading variant="small" title="Modifier les noms" description="Les codes restent inchangés pour préserver les références existantes." />
                {buildings.map(building => <div key={building.id} className="flex flex-wrap items-center justify-between gap-3 border-b py-3 last:border-0">
                    <div><p className="font-medium">Bâtiment {building.code} — {building.name}</p><Button type="button" size="sm" variant="outline" className="mt-2" onClick={() => renameBuilding(building)}>Modifier le nom du bâtiment</Button></div>
                    <div className="space-y-2">{building.cells.map(cell => <div key={cell.id} className="flex flex-wrap items-center justify-between gap-3"><span>{cell.code}{cell.name ? ` — ${cell.name}` : ''}</span><Button type="button" size="sm" variant="ghost" onClick={() => renameCell(cell)}>Modifier le nom de la cellule</Button></div>)}</div>
                </div>)}
            </section>
            <form onSubmit={submitAssignment} className="grid gap-4 rounded-lg border p-5 md:grid-cols-[1fr_1fr_auto] md:items-end"><div><h2 className="mb-3 font-semibold">Affecter un détenu à une cellule</h2><SearchableSelect label="Détenu non affecté" placeholder="Numéro de dossier, nom ou prénom" searchUrl={`/establishments/${establishment.id}/unassigned-detainees`} selected={selectedAssignmentDetainee} onSelect={option => { setSelectedAssignmentDetainee(option); assignmentForm.setData('detainee_id', option?.id.toString() ?? ''); }} error={assignmentForm.errors.detainee_id} disabled={!hasUnassignedDetainees} /></div><SearchableSelect label="Cellule (capacité indicative)" placeholder="Code de cellule ou bâtiment" searchUrl={`/establishments/${establishment.id}/available-cells`} selected={selectedAssignmentCell} onSelect={option => { setSelectedAssignmentCell(option); assignmentForm.setData('cell_id', option?.id.toString() ?? ''); }} error={assignmentForm.errors.cell_id} disabled={!buildings.some(building => building.cells.length > 0)} /><Button disabled={assignmentForm.processing || !hasUnassignedDetainees || !selectedAssignmentCell || !selectedAssignmentDetainee}>Enregistrer</Button></form>
            {!hasUnassignedDetainees && <p className="text-sm text-muted-foreground">Aucun détenu non affecté n’est actuellement enregistré dans cet établissement.</p>}
        </div>
    </>;
}
EstablishmentStructure.layout = { breadcrumbs: [{ title: 'Établissements', href: '/establishments' }, { title: 'Bâtiments et cellules', href: '#' }] };

