import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Establishment = {
    id: number; code: string; name: string; province: string | null; city: string | null;
    address: string | null; phone: string | null; email: string | null; status: 'active' | 'inactive';
    facility_type: string | null; administration_type: 'civil' | 'military' | null;
    penitentiary_region: string | null; court_jurisdiction: string | null;
    authorization_reference: string | null; authorization_date: string | null;
    women_dedicated: boolean; separate_women_quarters: boolean; separate_pretrial_quarters: boolean;
};

type FormData = Omit<Establishment, 'id'>;
type Props = { establishment: Establishment };

const facilityTypes = [
    { value: 'arrest_correction', label: 'Maison d’arrêt et de correction' },
    { value: 'central_prison', label: 'Prison centrale' },
    { value: 'detention_center', label: 'Centre de détention' },
    { value: 'high_security_prison', label: 'Prison de haute sécurité' },
    { value: 'military_prison', label: 'Prison militaire' },
];
const textFields: { key: keyof FormData; label: string; required?: boolean; type?: string }[] = [
    { key: 'code', label: 'Code', required: true }, { key: 'name', label: 'Nom de l’établissement', required: true },
    { key: 'province', label: 'Province' }, { key: 'city', label: 'Ville' }, { key: 'address', label: 'Adresse' },
    { key: 'phone', label: 'Téléphone' }, { key: 'email', label: 'Courriel', type: 'email' },
    { key: 'penitentiary_region', label: 'Région pénitentiaire' }, { key: 'court_jurisdiction', label: 'Ressort judiciaire' },
    { key: 'authorization_reference', label: 'Référence de l’acte de création' }, { key: 'authorization_date', label: 'Date de l’acte de création', type: 'date' },
];
const checks: { key: 'women_dedicated' | 'separate_women_quarters' | 'separate_pretrial_quarters'; label: string }[] = [
    { key: 'women_dedicated', label: 'Établissement réservé aux femmes' },
    { key: 'separate_women_quarters', label: 'Quartiers séparés pour les femmes' },
    { key: 'separate_pretrial_quarters', label: 'Quartiers séparés pour les prévenus' },
];

export default function EstablishmentEdit({ establishment }: Props) {
    const { data, setData, put, processing, errors } = useForm<FormData>({
        code: establishment.code, name: establishment.name, province: establishment.province ?? '', city: establishment.city ?? '',
        address: establishment.address ?? '', phone: establishment.phone ?? '', email: establishment.email ?? '', status: establishment.status,
        facility_type: establishment.facility_type ?? 'arrest_correction', administration_type: establishment.administration_type ?? 'civil',
        penitentiary_region: establishment.penitentiary_region ?? '', court_jurisdiction: establishment.court_jurisdiction ?? '',
        authorization_reference: establishment.authorization_reference ?? '', authorization_date: establishment.authorization_date ?? '',
        women_dedicated: establishment.women_dedicated, separate_women_quarters: establishment.separate_women_quarters,
        separate_pretrial_quarters: establishment.separate_pretrial_quarters,
    });
    function submit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); put(`/establishments/${establishment.id}`); }

    return (
        <>
            <Head title="Modifier l’établissement" />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Heading variant="small" title="Modifier l’établissement" description="Mets à jour les données administratives et la classification prévue par la loi." />
                <form onSubmit={submit} className="space-y-5 rounded-lg border p-6">
                    {textFields.slice(0, 7).map(({ key, label, required, type = 'text' }) => (
                        <div key={key} className="space-y-2"><label htmlFor={key} className="text-sm font-medium">{label}{required ? ' *' : ''}</label><input id={key} type={type} value={String(data[key])} onChange={(event) => setData(key, event.target.value)} required={required} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />{errors[key] ? <p className="text-sm text-destructive">{errors[key]}</p> : null}</div>
                    ))}
                    <div className="space-y-2"><label htmlFor="facility_type" className="text-sm font-medium">Type d’établissement</label><select id="facility_type" value={data.facility_type ?? ''} onChange={(event) => setData('facility_type', event.target.value)} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">{facilityTypes.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</select>{errors.facility_type ? <p className="text-sm text-destructive">{errors.facility_type}</p> : null}</div>
                    <div className="space-y-2"><label htmlFor="administration_type" className="text-sm font-medium">Administration</label><select id="administration_type" value={data.administration_type ?? 'civil'} onChange={(event) => setData('administration_type', event.target.value as FormData['administration_type'])} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option value="civil">Civile</option><option value="military">Militaire</option></select>{errors.administration_type ? <p className="text-sm text-destructive">{errors.administration_type}</p> : null}</div>
                    {textFields.slice(7).map(({ key, label, type = 'text' }) => (
                        <div key={key} className="space-y-2"><label htmlFor={key} className="text-sm font-medium">{label}</label><input id={key} type={type} value={String(data[key])} onChange={(event) => setData(key, event.target.value)} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />{errors[key] ? <p className="text-sm text-destructive">{errors[key]}</p> : null}</div>
                    ))}
                    <div className="space-y-3 rounded-md border p-4"><p className="text-sm font-medium">Organisation des quartiers</p>{checks.map(({ key, label }) => <label key={key} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data[key]} onChange={(event) => setData(key, event.target.checked)} />{label}</label>)}</div>
                    <div className="space-y-2"><label htmlFor="status" className="text-sm font-medium">Statut</label><select id="status" value={data.status} onChange={(event) => setData('status', event.target.value as FormData['status'])} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option value="active">Actif</option><option value="inactive">Inactif</option></select></div>
                    <div className="flex items-center gap-3"><Button type="submit" disabled={processing}>{processing ? 'Mise à jour…' : 'Mettre à jour l’établissement'}</Button><Button variant="outline" asChild><Link href="/establishments">Annuler</Link></Button></div>
                </form>
            </div>
        </>
    );
}
EstablishmentEdit.layout = { breadcrumbs: [{ title: 'Établissements', href: '/establishments' }, { title: 'Modifier', href: '/establishments' }] };
