import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ClipboardCheck, ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Draft = {
    id: number; offline_reference: string; status: 'pending_review' | 'approved' | 'rejected'; created_at: string;
    reviewed_at: string | null; review_note: string | null; establishment: { code: string; name: string };
    submitter: string | null; reviewer: string | null;
    payload: { family_name: string; post_name?: string | null; given_names: string; date_of_birth: string | null; sex: string; case_reference: string | null; case_type: string; court_name: string; court_level: string | null; judgment_final_at: string | null; order_reference: string; issuing_authority: string; issued_at: string | null; effective_at: string; expires_at: string | null; unit_reference: string | null; building_code: string; cell_code: string };
};
type Magistrate = { id: number; family_name: string; post_name: string | null; given_names: string; function: string; court_name: string };
type Props = { drafts: Draft[]; magistrates: Magistrate[]; success: string | null };

function AdmissionDraftCard({ draft, magistrates }: { draft: Draft; magistrates: Magistrate[] }) {
    const [showReject, setShowReject] = useState(false);
    const approve = useForm({ magistrate_id: '' });
    const reject = useForm({ review_note: '' });
    const matchingMagistrates = magistrates.filter(item => item.court_name.toLocaleLowerCase() === draft.payload.court_name.toLocaleLowerCase());
    function approveAdmission(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        approve.transform(data => ({ ...data, magistrate_id: data.magistrate_id || null })).post(`/admission-reviews/${draft.id}/approve`, { preserveScroll: true });
    }
    function rejectAdmission(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        reject.post(`/admission-reviews/${draft.id}/reject`, { preserveScroll: true, onSuccess: () => setShowReject(false) });
    }
    const payload = draft.payload;
    return <article className="space-y-4 rounded-lg border bg-card p-5">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-semibold">{payload.family_name} {payload.post_name ?? ''} {payload.given_names}</h2><p className="text-sm text-muted-foreground">{draft.establishment.code} — {draft.establishment.name} · transmise le {new Date(draft.created_at).toLocaleString('fr-FR')} · par {draft.submitter ?? 'compte supprimé'}</p></div><span className={`rounded-full px-3 py-1 text-xs font-medium ${draft.status === 'pending_review' ? 'bg-amber-100 text-amber-900' : draft.status === 'approved' ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900'}`}>{draft.status === 'pending_review' ? 'À vérifier' : draft.status === 'approved' ? 'Approuvée' : 'Rejetée'}</span></div>
        <div className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"><p><b>Naissance :</b> {payload.date_of_birth || 'Non précisée'} · {payload.sex === 'female' ? 'Féminin' : payload.sex === 'male' ? 'Masculin' : 'Non précisé'}</p><p><b>Dossier :</b> {payload.case_reference || 'Référence non précisée'} · {payload.case_type === 'pretrial' ? 'Prévenu' : 'Condamné'}</p><p><b>Juridiction :</b> {payload.court_name}{payload.court_level ? ` · ${payload.court_level}` : ''}</p><p><b>Ordre :</b> {payload.order_reference} · émis par {payload.issuing_authority}</p><p><b>Dates :</b> prise d’effet {payload.effective_at}{payload.issued_at ? ` · délivré le ${payload.issued_at}` : ''}{payload.expires_at ? ` · expire le ${payload.expires_at}` : ''}</p><p><b>Affectation proposée :</b> bâtiment {payload.building_code} · cellule {payload.cell_code}{payload.unit_reference ? ` · ${payload.unit_reference}` : ''}</p>{payload.judgment_final_at ? <p><b>Jugement définitif :</b> {payload.judgment_final_at}</p> : null}</div>
        {draft.status === 'pending_review' ? <>
            <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"><ShieldAlert size={18} className="mt-0.5 shrink-0" />Avant d’approuver, vérifiez l’ordre original et confirmez que l’autorité, la référence, les dates, la juridiction et la cellule sont correctes. L’approbation crée le dossier officiel du détenu.</p>
            <form onSubmit={approveAdmission} className="flex flex-wrap items-end gap-3"><label className="grid min-w-64 gap-1 text-sm">Magistrat (facultatif){matchingMagistrates.length ? <select className="rounded-md border bg-background px-3 py-2" value={approve.data.magistrate_id} onChange={event => approve.setData('magistrate_id', event.target.value)}><option value="">Non renseigné</option>{matchingMagistrates.map(item => <option key={item.id} value={item.id}>{item.family_name} {item.post_name ?? ''} {item.given_names} — {item.function}</option>)}</select> : <span className="text-xs text-muted-foreground">Aucun magistrat actif enregistré pour cette juridiction.</span>}</label><Button disabled={approve.processing}><ClipboardCheck size={16} /> Ordre vérifié : approuver et admettre</Button><Button type="button" variant="outline" onClick={() => setShowReject(value => !value)}>Rejeter</Button></form>
            {approve.errors.magistrate_id ? <p className="text-sm text-destructive">{approve.errors.magistrate_id}</p> : null}
            {showReject ? <form onSubmit={rejectAdmission} className="space-y-2 rounded-md border p-3"><label className="grid gap-1 text-sm">Motif du rejet<textarea required maxLength={2000} rows={3} className="rounded-md border bg-background p-2" value={reject.data.review_note} onChange={event => reject.setData('review_note', event.target.value)} /></label>{reject.errors.review_note ? <p className="text-sm text-destructive">{reject.errors.review_note}</p> : null}<Button variant="destructive" disabled={reject.processing}>Confirmer le rejet</Button></form> : null}
        </> : <p className="text-sm text-muted-foreground">Traitée le {draft.reviewed_at ? new Date(draft.reviewed_at).toLocaleString('fr-FR') : '—'} par {draft.reviewer ?? 'compte supprimé'}{draft.review_note ? ` · Motif : ${draft.review_note}` : ''}</p>}
    </article>;
}

export default function AdmissionReviewsIndex({ drafts, magistrates, success }: Props) {
    return <><Head title="Pré-admissions à vérifier" /><main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-5 md:p-8"><div className="flex flex-wrap items-center justify-between gap-3"><Heading variant="small" title="Pré-admissions à vérifier" description="Les saisies hors connexion restent des brouillons jusqu’à la vérification en ligne de l’ordre de détention." /><Button variant="outline" asChild><Link href="/detainees">Dossiers des détenus</Link></Button></div>{success ? <p role="status" className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">{success}</p> : null}{drafts.length ? <div className="space-y-4">{drafts.map(draft => <AdmissionDraftCard key={draft.id} draft={draft} magistrates={magistrates} />)}</div> : <p className="rounded-lg border p-8 text-center text-sm text-muted-foreground">Aucune pré-admission transmise.</p>}</main></>;
}

AdmissionReviewsIndex.layout = { breadcrumbs: [{ title: 'Pré-admissions', href: '/admission-reviews' }] };
