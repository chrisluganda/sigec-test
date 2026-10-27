import { FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, ClipboardList, UserRoundPlus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { SearchableSelect, SearchOption } from '@/components/searchable-select';

type Establishment = { id: number; code: string; name: string };
type VisitorIdentity = {
    id: number;
    family_name: string;
    post_name: string | null;
    given_names: string;
    date_of_birth: string | null;
    place_of_birth: string | null;
    document_type: string | null;
    document_reference: string | null;
    phone: string | null;
};
type Props = {
    establishments: Establishment[];
    selectedEstablishmentId: number;
    hasDetainees: boolean;
    selectedVisitor: VisitorIdentity | null;
    selectedDetainee: SearchOption | null;
    success: string | null;
};
const visitTypes: Record<string, string> = {
    family: 'Familiale',
    legal: 'Juridique',
    official: 'Officielle',
    other: 'Autre',
};
const inputClass =
    'mt-1 w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20';

export default function CreateVisit({
    establishments,
    selectedEstablishmentId,
    hasDetainees,
    selectedVisitor: initialVisitor,
    selectedDetainee: initialDetainee,
    success,
}: Props) {
    const [selectedDetainee, setSelectedDetainee] =
        useState<SearchOption | null>(initialDetainee);
    const form = useForm({
        establishment_id: selectedEstablishmentId,
        detainee_id: initialDetainee?.id.toString() ?? '',
        visitor_id: initialVisitor?.id.toString() ?? '',
        family_name: initialVisitor?.family_name ?? '',
        post_name: initialVisitor?.post_name ?? '',
        given_names: initialVisitor?.given_names ?? '',
        date_of_birth: initialVisitor?.date_of_birth ?? '',
        place_of_birth: initialVisitor?.place_of_birth ?? '',
        document_type: initialVisitor?.document_type ?? '',
        document_reference: initialVisitor?.document_reference ?? '',
        phone: initialVisitor?.phone ?? '',
        visit_type: 'family',
        relationship: '',
    });
    useEffect(() => {
        setSelectedDetainee(initialDetainee);
        form.setData({
            ...form.data,
            establishment_id: selectedEstablishmentId,
            detainee_id: initialDetainee?.id.toString() ?? '',
            visitor_id: initialVisitor?.id.toString() ?? '',
            family_name: initialVisitor?.family_name ?? '',
            post_name: initialVisitor?.post_name ?? '',
            given_names: initialVisitor?.given_names ?? '',
            date_of_birth: initialVisitor?.date_of_birth ?? '',
            place_of_birth: initialVisitor?.place_of_birth ?? '',
            document_type: initialVisitor?.document_type ?? '',
            document_reference: initialVisitor?.document_reference ?? '',
            phone: initialVisitor?.phone ?? '',
        });
    }, [selectedEstablishmentId, initialVisitor?.id, initialDetainee?.id]);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/visits');
    }
    const steps = [
        'Personne détenue',
        'Identité du visiteur',
        'Détails de la visite',
    ];
    return (
        <>
            <Head title="Enregistrer une arrivée" />
            <main className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:gap-8 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-gradient-to-r from-primary/5 via-card to-card p-5 shadow-sm md:p-7">
                    <Heading
                        variant="small"
                        title="Enregistrer une arrivée"
                        description="Choisissez le détenu, saisissez l’identité du visiteur, puis enregistrez son arrivée. La visite sera clôturée à sa sortie."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href="/offline-visits.html">
                                Saisie hors connexion
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={`/visits?establishment_id=${selectedEstablishmentId}`}
                            >
                                Retour au journal
                            </Link>
                        </Button>
                    </div>
                </header>
                {success ? (
                    <p
                        role="status"
                        className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900"
                    >
                        {success}
                    </p>
                ) : null}
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <label
                        htmlFor="visit-establishment"
                        className="text-sm font-medium"
                    >
                        Établissement
                    </label>
                    <select
                        id="visit-establishment"
                        value={selectedEstablishmentId}
                        onChange={(event) =>
                            router.get('/visits/create', {
                                establishment_id: Number(event.target.value),
                            })
                        }
                        className={inputClass}
                    >
                        {establishments.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.code} — {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                <ol
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label="Étapes d’enregistrement"
                >
                    {steps.map((step, index) => (
                        <li
                            key={step}
                            className="flex items-center gap-3 rounded-xl border bg-card p-3 shadow-sm"
                        >
                            <span
                                className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${index === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}
                            >
                                {index + 1}
                            </span>
                            <span className="text-sm font-medium">{step}</span>
                            {index < 2 ? (
                                <ArrowRight className="ml-auto hidden size-4 text-muted-foreground sm:block" />
                            ) : null}
                        </li>
                    ))}
                </ol>
                {hasDetainees ? (
                    <form onSubmit={submit} className="space-y-6">
                        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-sm md:p-6">
                            <div className="flex items-center gap-3">
                                <span className="rounded-xl bg-primary/10 p-2.5 text-primary">
                                    <ClipboardList size={20} />
                                </span>
                                <div>
                                    <h2 className="font-semibold">
                                        1. Détenu à visiter
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Recherchez le dossier demandé.
                                    </p>
                                </div>
                            </div>
                            <SearchableSelect
                                label="Personne détenue"
                                placeholder="Numéro de dossier, nom ou prénom"
                                searchUrl={`/establishments/${selectedEstablishmentId}/visit-detainees`}
                                selected={selectedDetainee}
                                onSelect={(option) => {
                                    setSelectedDetainee(option);
                                    form.setData(
                                        'detainee_id',
                                        option?.id.toString() ?? '',
                                    );
                                }}
                                error={form.errors.detainee_id}
                            />
                        </section>
                        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-sm md:p-6">
                            <div className="flex items-center gap-3">
                                <span className="rounded-xl bg-primary/10 p-2.5 text-primary">
                                    <UserRoundPlus size={20} />
                                </span>
                                <div>
                                    <h2 className="font-semibold">
                                        2. Identité du visiteur
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {initialVisitor
                                            ? 'Visiteur déjà inscrit : vérifiez ses renseignements.'
                                            : 'Saisissez les renseignements d’identité.'}
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="text-sm font-medium">
                                    Nom
                                    <input
                                        required
                                        value={form.data.family_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'family_name',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                    {form.errors.family_name && (
                                        <small className="text-destructive">
                                            {form.errors.family_name}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm font-medium">
                                    Post-nom
                                    <input
                                        value={form.data.post_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'post_name',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-sm font-medium">
                                    Prénom
                                    <input
                                        required
                                        value={form.data.given_names}
                                        onChange={(event) =>
                                            form.setData(
                                                'given_names',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                    {form.errors.given_names && (
                                        <small className="text-destructive">
                                            {form.errors.given_names}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm font-medium">
                                    Date de naissance{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        type="date"
                                        value={form.data.date_of_birth}
                                        onChange={(event) =>
                                            form.setData(
                                                'date_of_birth',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                    {form.errors.date_of_birth && (
                                        <small className="text-destructive">
                                            {form.errors.date_of_birth}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm font-medium">
                                    Lieu de naissance{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        value={form.data.place_of_birth}
                                        onChange={(event) =>
                                            form.setData(
                                                'place_of_birth',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                    {form.errors.place_of_birth && (
                                        <small className="text-destructive">
                                            {form.errors.place_of_birth}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm font-medium">
                                    Téléphone{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-sm font-medium">
                                    Type de pièce{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        value={form.data.document_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'document_type',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Carte d’identité…"
                                        className={inputClass}
                                    />
                                </label>
                                <label className="text-sm font-medium sm:col-span-2">
                                    Référence de la pièce{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        value={form.data.document_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'document_reference',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </label>
                            </div>
                        </section>
                        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-sm md:p-6">
                            <div className="flex items-center gap-3">
                                <span className="rounded-xl bg-primary/10 p-2.5 text-primary">
                                    <ClipboardList size={20} />
                                </span>
                                <div>
                                    <h2 className="font-semibold">
                                        3. Confirmer l’arrivée
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        L’arrivée est horodatée automatiquement.
                                        À la sortie, la visite sera marquée
                                        terminée dans le journal.
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="text-sm font-medium">
                                    Type de visite
                                    <select
                                        value={form.data.visit_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'visit_type',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    >
                                        {Object.entries(visitTypes).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    {form.errors.visit_type && (
                                        <small className="text-destructive">
                                            {form.errors.visit_type}
                                        </small>
                                    )}
                                </label>
                                <label className="text-sm font-medium">
                                    Lien avec le détenu{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                    <input
                                        value={form.data.relationship}
                                        onChange={(event) =>
                                            form.setData(
                                                'relationship',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Parent, avocat…"
                                        className={inputClass}
                                    />
                                </label>
                            </div>
                        </section>
                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing || !selectedDetainee}
                            >
                                {form.processing
                                    ? 'Enregistrement…'
                                    : 'Enregistrer l’arrivée'}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        Aucune personne détenue n’est actuellement présente dans
                        cet établissement.
                    </p>
                )}
            </main>
        </>
    );
}
CreateVisit.layout = {
    breadcrumbs: [
        { title: 'Visites', href: '/visits' },
        { title: 'Enregistrer une arrivée', href: '/visits/create' },
    ],
};
