import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Building = { id: number; label: string };
type CellOption = { id: number; label: string; description: string };
type Establishment = {
    id: number;
    code: string;
    name: string;
    has_active_cells: boolean;
    available_buildings: Building[];
};
type Magistrate = {
    id: number;
    family_name: string;
    post_name: string | null;
    given_names: string;
    function: string;
    court_name: string;
    court_level: string | null;
};
type FormData = {
    detainee_id: number | '';
    establishment_id: number | '';
    family_name: string;
    post_name: string;
    given_names: string;
    date_of_birth: string;
    sex: 'female' | 'male' | 'unknown';
    case_reference: string;
    case_type: 'pretrial' | 'convicted';
    magistrate_id: string;
    court_name: string;
    court_level: string;
    judgment_final_at: string;
    order_reference: string;
    issuing_authority: string;
    issued_at: string;
    effective_at: string;
    expires_at: string;
    unit_reference: string;
    cell_id: string;
    title_verified: boolean;
    photo: File | null;
    fingerprint_template: File | null;
    finger_position: string;
    capture_device: string;
    collection_basis: string;
};
type TextFieldKey = Exclude<
    keyof FormData,
    | 'establishment_id'
    | 'sex'
    | 'title_verified'
    | 'photo'
    | 'fingerprint_template'
>;
type ExistingDetainee = {
    id: number;
    record_number: string;
    family_name: string;
    post_name: string | null;
    given_names: string;
    date_of_birth: string | null;
    sex: 'female' | 'male' | 'unknown';
};
type Props = {
    establishments: Establishment[];
    magistrates: Magistrate[];
    existingDetainee: ExistingDetainee | null;
};

export default function DetaineeCreate({
    establishments,
    magistrates,
    existingDetainee,
}: Props) {
    const [buildingId, setBuildingId] = useState('');
    const [cells, setCells] = useState<CellOption[]>([]);
    const [cameraStream, setCameraStream] = useState<MediaStream | null>(null);
    const [cameraError, setCameraError] = useState('');
    const videoRef = useRef<HTMLVideoElement>(null);
    const { data, setData, post, processing, errors } = useForm<FormData>({
        detainee_id: existingDetainee?.id ?? '',
        establishment_id:
            establishments.length === 1 ? establishments[0].id : '',
        family_name: existingDetainee?.family_name ?? '',
        post_name: existingDetainee?.post_name ?? '',
        given_names: existingDetainee?.given_names ?? '',
        date_of_birth: existingDetainee?.date_of_birth ?? '',
        sex: existingDetainee?.sex ?? 'unknown',
        case_reference: '',
        case_type: 'pretrial',
        magistrate_id: '',
        court_name: '',
        court_level: '',
        judgment_final_at: '',
        order_reference: '',
        issuing_authority: '',
        issued_at: '',
        effective_at: '',
        expires_at: '',
        unit_reference: '',
        cell_id: '',
        title_verified: false,
        photo: null,
        fingerprint_template: null,
        finger_position: '',
        capture_device: '',
        collection_basis: '',
    });
    useEffect(() => {
        if (videoRef.current) videoRef.current.srcObject = cameraStream;
        return () => cameraStream?.getTracks().forEach((track) => track.stop());
    }, [cameraStream]);
    async function startCamera() {
        setCameraError('');
        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError(
                'La caméra n’est pas accessible dans ce navigateur ou cette connexion.',
            );
            return;
        }
        try {
            setCameraStream(
                await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false,
                }),
            );
        } catch {
            setCameraError(
                'Accès à la caméra refusé ou caméra indisponible. Vous pouvez choisir une photo depuis vos fichiers.',
            );
        }
    }
    function capturePhoto() {
        const video = videoRef.current;
        if (!video || !video.videoWidth || !video.videoHeight) {
            setCameraError('Attendez que l’aperçu de la caméra soit prêt.');
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        if (!context) {
            setCameraError('La capture de la photo a échoué.');
            return;
        }
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    setCameraError('La création du fichier photo a échoué.');
                    return;
                }
                setData(
                    'photo',
                    new File([blob], `photo-detenu-${Date.now()}.jpg`, {
                        type: 'image/jpeg',
                    }),
                );
                setCameraStream(null);
            },
            'image/jpeg',
            0.9,
        );
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/detainees');
    }
    const selectedEstablishment = establishments.find(
        (item) => item.id === Number(data.establishment_id),
    );
    useEffect(() => {
        setData('cell_id', '');
        setCells([]);
        if (!selectedEstablishment || !buildingId) return;
        const controller = new AbortController();
        fetch(
            `/establishments/${selectedEstablishment.id}/buildings/${buildingId}/admission-cells`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            },
        )
            .then((response) =>
                response.ok
                    ? response.json()
                    : Promise.reject(new Error('Chargement impossible')),
            )
            .then((options: CellOption[]) => setCells(options))
            .catch((error) => {
                if (error.name !== 'AbortError') setCells([]);
            });
        return () => controller.abort();
    }, [buildingId, selectedEstablishment?.id]);
    const textFields: {
        key: TextFieldKey;
        label: string;
        type?: string;
        required?: boolean;
    }[] = [
        { key: 'family_name', label: 'Nom', required: true },
        { key: 'post_name', label: 'Post-nom (facultatif)' },
        { key: 'given_names', label: 'Prénom', required: true },
        { key: 'date_of_birth', label: 'Date de naissance', type: 'date' },
        { key: 'case_reference', label: 'Référence de l’affaire' },
        { key: 'court_name', label: 'Tribunal / juridiction', required: true },
        { key: 'court_level', label: 'Niveau de juridiction' },
        {
            key: 'judgment_final_at',
            label: 'Date de la décision définitive',
            type: 'date',
        },
        {
            key: 'order_reference',
            label: 'Référence du titre légal',
            required: true,
        },
        {
            key: 'issuing_authority',
            label: 'Autorité ayant délivré le titre',
            required: true,
        },
        {
            key: 'issued_at',
            label: 'Date de délivrance du titre',
            type: 'date',
        },
        {
            key: 'effective_at',
            label: 'Date de prise d’effet du titre',
            type: 'date',
            required: true,
        },
        {
            key: 'expires_at',
            label: 'Date d’expiration du titre',
            type: 'date',
        },
        {
            key: 'unit_reference',
            label: 'Quartier / unité (précision complémentaire)',
        },
    ];

    return (
        <>
            <Head title="Nouvelle admission" />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <Heading
                    variant="small"
                    title="Enregistrer une admission"
                    description={
                        existingDetainee
                            ? 'Réadmission : le dossier existant sera conservé et un nouveau séjour sera ajouté après vérification du nouveau titre légal.'
                            : 'Enregistre les informations d’identité et le titre légal après vérification de l’original.'
                    }
                />
                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-lg border p-6"
                >
                    {existingDetainee ? (
                        <div className="rounded-md border bg-muted/40 p-4 text-sm">
                            <p className="font-medium">Réutilisation du dossier existant</p>
                            <p className="mt-1 text-muted-foreground">
                                Dossier {existingDetainee.record_number.split('-')[0]} — {existingDetainee.family_name}{' '}
                                {existingDetainee.post_name ?? ''} {existingDetainee.given_names}. L’identité et l’historique seront conservés.
                            </p>
                        </div>
                    ) : null}
                    <div className="space-y-2">
                        <label
                            htmlFor="establishment_id"
                            className="text-sm font-medium"
                        >
                            Établissement
                        </label>
                        <select
                            id="establishment_id"
                            value={data.establishment_id}
                            onChange={(event) => {
                                setData(
                                    'establishment_id',
                                    Number(event.target.value),
                                );
                                setData('cell_id', '');
                                setBuildingId('');
                            }}
                            required
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Choisir un établissement</option>
                            {establishments.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.code} — {item.name}
                                </option>
                            ))}
                        </select>
                        {errors.establishment_id ? (
                            <p className="text-sm text-destructive">
                                {errors.establishment_id}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <label
                                htmlFor="building_id"
                                className="text-sm font-medium"
                            >
                                Bâtiment *
                            </label>
                            <select
                                id="building_id"
                                value={buildingId}
                                onChange={(event) => {
                                    setBuildingId(event.target.value);
                                    setData('cell_id', '');
                                }}
                                disabled={
                                    !selectedEstablishment?.has_active_cells
                                }
                                required
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">
                                    {selectedEstablishment
                                        ? selectedEstablishment.has_active_cells
                                            ? 'Choisir un bâtiment'
                                            : 'Aucun bâtiment avec cellule active'
                                        : 'Choisissez d’abord un établissement'}
                                </option>
                                {selectedEstablishment?.available_buildings.map(
                                    (building) => (
                                        <option
                                            key={building.id}
                                            value={building.id}
                                        >
                                            {building.label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="cell_id"
                                className="text-sm font-medium"
                            >
                                Cellule *
                            </label>
                            <select
                                id="cell_id"
                                value={data.cell_id}
                                onChange={(event) =>
                                    setData('cell_id', event.target.value)
                                }
                                disabled={!buildingId}
                                required
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">
                                    {buildingId
                                        ? cells.length
                                            ? 'Choisir une cellule'
                                            : 'Aucune cellule active'
                                        : 'Choisissez d’abord un bâtiment'}
                                </option>
                                {cells.map((cell) => (
                                    <option key={cell.id} value={cell.id}>
                                        {cell.label} — {cell.description}
                                    </option>
                                ))}
                            </select>
                            {errors.cell_id ? (
                                <p className="text-sm text-destructive">
                                    {errors.cell_id}
                                </p>
                            ) : null}
                        </div>
                        <p className="text-xs text-muted-foreground sm:col-span-2">
                            La capacité est indicative ; une affectation au-delà
                            de cette capacité sera signalée comme surpopulation.
                        </p>
                    </div>
                    {selectedEstablishment &&
                    !selectedEstablishment.has_active_cells ? (
                        <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                            Aucune cellule active n’est enregistrée dans cet
                            établissement. Ajoutez une cellule active pour
                            poursuivre l’admission.
                        </p>
                    ) : null}
                    {textFields
                        .slice(0, 3)
                        .map(({ key, label, type = 'text', required }) => (
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
                                    readOnly={Boolean(existingDetainee)}
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
                        <label htmlFor="sex" className="text-sm font-medium">
                            Sexe
                        </label>
                        <select
                            id="sex"
                            value={data.sex}
                            disabled={Boolean(existingDetainee)}
                            onChange={(event) =>
                                setData(
                                    'sex',
                                    event.target.value as FormData['sex'],
                                )
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="female">Femme</option>
                            <option value="male">Homme</option>
                            <option value="unknown">À confirmer</option>
                        </select>
                    </div>
                    {!existingDetainee ? (
                    <section className="space-y-3 rounded-md border p-4">
                        <div>
                            <h2 className="font-medium">Photo et empreinte</h2>
                            <p className="text-xs text-muted-foreground">
                                La photo est facultative. Pour l’empreinte,
                                joignez le modèle exporté par un lecteur
                                compatible ; le modèle est chiffré au stockage.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="photo"
                                className="text-sm font-medium"
                            >
                                Photo du détenu
                            </label>
                            {cameraStream ? (
                                <div className="space-y-2">
                                    <video
                                        ref={videoRef}
                                        autoPlay
                                        playsInline
                                        muted
                                        aria-label="Aperçu de la caméra"
                                        className="max-h-72 w-full rounded-md bg-black object-contain"
                                    />
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            onClick={capturePhoto}
                                        >
                                            Prendre la photo
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setCameraStream(null)
                                            }
                                        >
                                            Arrêter la caméra
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={startCamera}
                                >
                                    Ouvrir la caméra
                                </Button>
                            )}
                            {cameraError ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {cameraError}
                                </p>
                            ) : null}
                            <input
                                id="photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    setData(
                                        'photo',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1"
                            />
                            {data.photo ? (
                                <p className="text-xs text-muted-foreground">
                                    Photo sélectionnée : {data.photo.name}
                                </p>
                            ) : null}
                            {errors.photo ? (
                                <p className="text-sm text-destructive">
                                    {errors.photo}
                                </p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="fingerprint_template"
                                className="text-sm font-medium"
                            >
                                Modèle d’empreinte (facultatif)
                            </label>
                            <input
                                id="fingerprint_template"
                                type="file"
                                accept=".bin,.dat,.template,application/octet-stream"
                                onChange={(event) =>
                                    setData(
                                        'fingerprint_template',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1"
                            />
                            {data.fingerprint_template ? (
                                <p className="text-xs text-muted-foreground">
                                    Fichier choisi :{' '}
                                    {data.fingerprint_template.name}
                                </p>
                            ) : null}
                            {errors.fingerprint_template ? (
                                <p className="text-sm text-destructive">
                                    {errors.fingerprint_template}
                                </p>
                            ) : null}
                        </div>
                        {data.fingerprint_template ? (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <label
                                        htmlFor="finger_position"
                                        className="text-sm font-medium"
                                    >
                                        Doigt *
                                    </label>
                                    <select
                                        id="finger_position"
                                        value={data.finger_position}
                                        onChange={(event) =>
                                            setData(
                                                'finger_position',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">
                                            Choisir le doigt
                                        </option>
                                        <option value="left_thumb">
                                            Pouce gauche
                                        </option>
                                        <option value="left_index">
                                            Index gauche
                                        </option>
                                        <option value="right_thumb">
                                            Pouce droit
                                        </option>
                                        <option value="right_index">
                                            Index droit
                                        </option>
                                        <option value="other">Autre</option>
                                    </select>
                                    {errors.finger_position ? (
                                        <p className="text-sm text-destructive">
                                            {errors.finger_position}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <label
                                        htmlFor="capture_device"
                                        className="text-sm font-medium"
                                    >
                                        Lecteur utilisé *
                                    </label>
                                    <input
                                        id="capture_device"
                                        value={data.capture_device}
                                        onChange={(event) =>
                                            setData(
                                                'capture_device',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        maxLength={255}
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.capture_device ? (
                                        <p className="text-sm text-destructive">
                                            {errors.capture_device}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <label
                                        htmlFor="collection_basis"
                                        className="text-sm font-medium"
                                    >
                                        Base légale ou référence d’autorisation
                                        *
                                    </label>
                                    <input
                                        id="collection_basis"
                                        value={data.collection_basis}
                                        onChange={(event) =>
                                            setData(
                                                'collection_basis',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        maxLength={255}
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.collection_basis ? (
                                        <p className="text-sm text-destructive">
                                            {errors.collection_basis}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}
                    </section>
                    ) : null}
                    <div className="space-y-2">
                        <label
                            htmlFor="case_type"
                            className="text-sm font-medium"
                        >
                            Statut judiciaire
                        </label>
                        <select
                            id="case_type"
                            value={data.case_type}
                            onChange={(event) =>
                                setData(
                                    'case_type',
                                    event.target.value as FormData['case_type'],
                                )
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="pretrial">Prévenu</option>
                            <option value="convicted">Condamné</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <label
                            htmlFor="magistrate_id"
                            className="text-sm font-medium"
                        >
                            Magistrat ayant délivré le titre (facultatif)
                        </label>
                        <select
                            id="magistrate_id"
                            value={data.magistrate_id}
                            onChange={(event) => {
                                const selected = magistrates.find(
                                    (item) =>
                                        item.id === Number(event.target.value),
                                );
                                setData('magistrate_id', event.target.value);
                                if (selected) {
                                    setData('court_name', selected.court_name);
                                    setData(
                                        'court_level',
                                        selected.court_level ?? '',
                                    );
                                    setData(
                                        'issuing_authority',
                                        `${selected.function} ${selected.family_name} ${selected.post_name ? `${selected.post_name} ` : ''}${selected.given_names}`,
                                    );
                                }
                            }}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">
                                Aucun magistrat du répertoire
                            </option>
                            {magistrates.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.family_name} {item.post_name ?? ''}{' '}
                                    {item.given_names} — {item.function},{' '}
                                    {item.court_name}
                                </option>
                            ))}
                        </select>
                        {errors.magistrate_id ? (
                            <p className="text-sm text-destructive">
                                {errors.magistrate_id}
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Le choix renseigne automatiquement la
                                juridiction et l’autorité émettrice, que vous
                                pouvez corriger si le titre l’exige.
                            </p>
                        )}
                    </div>
                    {textFields
                        .slice(3)
                        .map(({ key, label, type = 'text', required }) => {
                            if (existingDetainee && key === 'date_of_birth')
                                return null;
                            if (
                                key === 'judgment_final_at' &&
                                data.case_type !== 'convicted'
                            )
                                return null;
                            return (
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
                                        required={
                                            required ||
                                            (key === 'judgment_final_at' &&
                                                data.case_type === 'convicted')
                                        }
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    {errors[key] ? (
                                        <p className="text-sm text-destructive">
                                            {errors[key]}
                                        </p>
                                    ) : null}
                                </div>
                            );
                        })}
                    <label className="flex items-start gap-2 rounded-md border p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={data.title_verified}
                            onChange={(event) =>
                                setData('title_verified', event.target.checked)
                            }
                            required
                            className="mt-1"
                        />
                        <span>
                            J’ai vérifié le titre légal original et sa validité.
                            Cette vérification sera enregistrée avec mon compte.
                        </span>
                    </label>
                    {errors.title_verified ? (
                        <p className="text-sm text-destructive">
                            {errors.title_verified}
                        </p>
                    ) : null}
                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                !selectedEstablishment?.has_active_cells ||
                                !buildingId ||
                                !data.cell_id
                            }
                        >
                            {processing
                                ? 'Enregistrement…'
                                : data.cell_id
                                  ? 'Enregistrer l’admission et l’affectation'
                                  : !buildingId
                                    ? 'Choisir un bâtiment pour continuer'
                                    : 'Choisir une cellule pour continuer'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/detainees">Annuler</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
DetaineeCreate.layout = {
    breadcrumbs: [
        { title: 'Personnes détenues', href: '/detainees' },
        { title: 'Nouvelle admission', href: '/detainees/create' },
    ],
};
