import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type FormData = {
    code: string;
    name: string;
    province: string;
    city: string;
    address: string;
    phone: string;
    email: string;
    status: 'active' | 'inactive';
    facility_type: string;
    administration_type: 'civil' | 'military';
    penitentiary_region: string;
    court_jurisdiction: string;
    authorization_reference: string;
    authorization_date: string;
    women_dedicated: boolean;
    separate_women_quarters: boolean;
    separate_pretrial_quarters: boolean;
};

const facilityTypes = [
    { value: 'arrest_correction', label: 'Maison d’arrêt et de correction' },
    { value: 'central_prison', label: 'Prison centrale' },
    { value: 'detention_center', label: 'Centre de détention' },
    { value: 'high_security_prison', label: 'Prison de haute sécurité' },
    { value: 'military_prison', label: 'Prison militaire' },
];

const textFields: {
    key: keyof FormData;
    label: string;
    required?: boolean;
    type?: string;
}[] = [
    { key: 'code', label: 'Code', required: true },
    { key: 'name', label: 'Nom de l’établissement', required: true },
    { key: 'province', label: 'Province' },
    { key: 'city', label: 'Ville' },
    { key: 'address', label: 'Adresse' },
    { key: 'phone', label: 'Téléphone' },
    { key: 'email', label: 'Courriel', type: 'email' },
    { key: 'penitentiary_region', label: 'Région pénitentiaire' },
    { key: 'court_jurisdiction', label: 'Ressort judiciaire' },
    {
        key: 'authorization_reference',
        label: 'Référence de l’acte de création',
    },
    {
        key: 'authorization_date',
        label: 'Date de l’acte de création',
        type: 'date',
    },
];

const checks: {
    key:
        | 'women_dedicated'
        | 'separate_women_quarters'
        | 'separate_pretrial_quarters';
    label: string;
}[] = [
    { key: 'women_dedicated', label: 'Établissement réservé aux femmes' },
    {
        key: 'separate_women_quarters',
        label: 'Quartiers séparés pour les femmes',
    },
    {
        key: 'separate_pretrial_quarters',
        label: 'Quartiers séparés pour les prévenus',
    },
];

export default function EstablishmentCreate() {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        code: '',
        name: '',
        province: '',
        city: '',
        address: '',
        phone: '',
        email: '',
        status: 'active',
        facility_type: 'arrest_correction',
        administration_type: 'civil',
        penitentiary_region: '',
        court_jurisdiction: '',
        authorization_reference: '',
        authorization_date: '',
        women_dedicated: false,
        separate_women_quarters: false,
        separate_pretrial_quarters: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/establishments');
    }

    return (
        <>
            <Head title="Nouvel établissement" />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Heading
                    variant="small"
                    title="Nouvel établissement"
                    description="Renseigne les données administratives et la classification prévue par la loi. Les champs Code et Nom sont obligatoires."
                />
                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-lg border p-6"
                >
                    {textFields
                        .slice(0, 7)
                        .map(({ key, label, required, type = 'text' }) => (
                            <div key={key} className="space-y-2">
                                <label
                                    htmlFor={key}
                                    className="text-sm font-medium"
                                >
                                    {label}
                                    {required ? ' *' : ''}
                                </label>
                                <input
                                    id={key}
                                    type={type}
                                    value={String(data[key])}
                                    onChange={(event) =>
                                        setData(key, event.target.value)
                                    }
                                    required={required}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                {errors[key] ? (
                                    <p className="text-sm text-destructive">
                                        {errors[key]}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    <div className="space-y-2">
                        <label
                            htmlFor="facility_type"
                            className="text-sm font-medium"
                        >
                            Type d’établissement
                        </label>
                        <select
                            id="facility_type"
                            value={data.facility_type}
                            onChange={(event) =>
                                setData('facility_type', event.target.value)
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            {facilityTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        {errors.facility_type ? (
                            <p className="text-sm text-destructive">
                                {errors.facility_type}
                            </p>
                        ) : null}
                    </div>
                    <div className="space-y-2">
                        <label
                            htmlFor="administration_type"
                            className="text-sm font-medium"
                        >
                            Administration
                        </label>
                        <select
                            id="administration_type"
                            value={data.administration_type}
                            onChange={(event) =>
                                setData(
                                    'administration_type',
                                    event.target
                                        .value as FormData['administration_type'],
                                )
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="civil">Civile</option>
                            <option value="military">Militaire</option>
                        </select>
                        {errors.administration_type ? (
                            <p className="text-sm text-destructive">
                                {errors.administration_type}
                            </p>
                        ) : null}
                    </div>
                    {textFields
                        .slice(7)
                        .map(({ key, label, type = 'text' }) => (
                            <div key={key} className="space-y-2">
                                <label
                                    htmlFor={key}
                                    className="text-sm font-medium"
                                >
                                    {label}
                                </label>
                                <input
                                    id={key}
                                    type={type}
                                    value={String(data[key])}
                                    onChange={(event) =>
                                        setData(key, event.target.value)
                                    }
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                {errors[key] ? (
                                    <p className="text-sm text-destructive">
                                        {errors[key]}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    <div className="space-y-3 rounded-md border p-4">
                        <p className="text-sm font-medium">
                            Organisation des quartiers
                        </p>
                        {checks.map(({ key, label }) => (
                            <label
                                key={key}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={data[key]}
                                    onChange={(event) =>
                                        setData(key, event.target.checked)
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <label htmlFor="status" className="text-sm font-medium">
                            Statut
                        </label>
                        <select
                            id="status"
                            value={data.status}
                            onChange={(event) =>
                                setData(
                                    'status',
                                    event.target.value as FormData['status'],
                                )
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                        </select>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Création…' : 'Créer l’établissement'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/establishments">Annuler</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

EstablishmentCreate.layout = {
    breadcrumbs: [
        { title: 'Établissements', href: '/establishments' },
        { title: 'Nouvel établissement', href: '/establishments/create' },
    ],
};
