import { FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Gavel } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Magistrate = { id: number; official_reference: string | null; family_name: string; post_name: string | null; given_names: string; function: string; court_name: string; court_level: string | null; jurisdiction: string | null; province: string | null; is_active: boolean; detention_orders_count: number };
type Props = { magistrates: Magistrate[]; success: string | null };

export default function MagistratesIndex({ magistrates, success }: Props) {
    const form = useForm({ official_reference: '', family_name: '', post_name: '', given_names: '', function: '', court_name: '', court_level: '', jurisdiction: '', province: '' });
    function submit(event: FormEvent) { event.preventDefault(); form.post('/magistrates', { onSuccess: () => form.reset() }); }
    function toggle(magistrate: Magistrate) { router.patch(`/magistrates/${magistrate.id}/status`, { is_active: !magistrate.is_active }); }
    return <>
        <Head title="Magistrats" />
        <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8">
            <div className="flex items-center gap-3"><Gavel className="size-6 text-primary" /><Heading variant="small" title="Répertoire des magistrats" description="Référentiel central des autorités judiciaires qui délivrent les titres de détention." /></div>
            {success ? <p className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">{success}</p> : null}
            <form onSubmit={submit} className="space-y-4 rounded-lg border bg-card p-5">
                <h2 className="font-semibold">Ajouter un magistrat</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {([{key:'family_name',label:'Nom',required:true},{key:'post_name',label:'Post-nom'},{key:'given_names',label:'Prénom',required:true},{key:'function',label:'Fonction',required:true},{key:'official_reference',label:'Référence officielle'},{key:'court_name',label:'Juridiction',required:true},{key:'court_level',label:'Niveau de juridiction'},{key:'jurisdiction',label:'Ressort'},{key:'province',label:'Province'}] as const).map(({key,label,required}) => <label key={key} className="text-sm">{label}{required ? ' *' : ''}<input required={required} maxLength={255} value={form.data[key]} onChange={event => form.setData(key, event.target.value)} className="mt-1 w-full rounded-md border bg-background px-3 py-2" />{form.errors[key] && <small className="text-destructive">{form.errors[key]}</small>}</label>)}
                </div>
                <Button disabled={form.processing}>{form.processing ? 'Enregistrement…' : 'Ajouter au répertoire'}</Button>
            </form>
            <section className="space-y-3"><Heading variant="small" title="Magistrats enregistrés" description="Désactiver un profil le retire des nouvelles admissions sans modifier les titres déjà délivrés." />
                {magistrates.length ? <div className="overflow-x-auto rounded-lg border"><table className="w-full min-w-[850px] text-left text-sm"><thead className="bg-muted/50"><tr><th className="px-4 py-3">Nom / référence</th><th className="px-4 py-3">Fonction</th><th className="px-4 py-3">Juridiction</th><th className="px-4 py-3">Ressort</th><th className="px-4 py-3">Titres associés</th><th className="px-4 py-3">Statut</th></tr></thead><tbody>{magistrates.map(item => <tr key={item.id} className="border-t"><td className="px-4 py-3">{item.family_name} {item.post_name ?? ''} {item.given_names}<div className="text-xs text-muted-foreground">{item.official_reference || 'Sans référence officielle'}</div></td><td className="px-4 py-3">{item.function}</td><td className="px-4 py-3">{item.court_name}{item.court_level ? <div className="text-xs text-muted-foreground">{item.court_level}</div> : null}</td><td className="px-4 py-3">{[item.jurisdiction, item.province].filter(Boolean).join(' · ') || '—'}</td><td className="px-4 py-3">{item.detention_orders_count}</td><td className="px-4 py-3"><Button size="sm" variant={item.is_active ? 'outline' : 'default'} onClick={() => toggle(item)}>{item.is_active ? 'Actif · désactiver' : 'Inactif · réactiver'}</Button></td></tr>)}</tbody></table></div> : <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">Aucun magistrat n’est encore inscrit au répertoire.</p>}
            </section>
        </main>
    </>;
}

MagistratesIndex.layout = { breadcrumbs: [{ title: 'Magistrats', href: '/magistrates' }] };
