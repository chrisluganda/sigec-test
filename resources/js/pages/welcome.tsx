import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    ClipboardCheck,
    LockKeyhole,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const signedIn = Boolean(auth.user);

    return (
        <>
            <Head title="SIGEC — Gestion carcérale" />
            <div className="min-h-screen overflow-hidden bg-[#f5f6f2] text-[#172b35]">
                <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
                    <Link
                        href="/"
                        className="flex items-center gap-3"
                        aria-label="SIGEC, accueil"
                    >
                        <span className="flex size-11 items-center justify-center rounded-2xl bg-[#173d48] text-[#d9c18a] shadow-sm">
                            <Building2 size={23} strokeWidth={1.8} />
                        </span>
                        <span className="leading-tight">
                            <span className="block text-lg font-bold tracking-[0.16em]">
                                SIGEC
                            </span>
                            <span className="text-[10px] font-medium tracking-[0.13em] text-slate-500 uppercase">
                                Gestion carcérale
                            </span>
                        </span>
                    </Link>
                    <nav className="flex items-center gap-3">
                        {signedIn ? (
                            <Link
                                href="/dashboard"
                                className="rounded-full bg-[#173d48] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#245664]"
                            >
                                Espace de gestion{' '}
                                <ArrowRight className="ml-2 inline" size={16} />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-full px-4 py-2.5 text-sm font-semibold text-[#173d48] hover:bg-white"
                                >
                                    Connexion
                                </Link>
                                <Link
                                    href={register()}
                                    className="hidden rounded-full bg-[#173d48] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#245664] sm:inline-flex"
                                >
                                    Créer un compte
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid max-w-7xl items-center gap-12 px-5 pt-8 pb-16 sm:px-8 sm:pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:px-12 lg:pt-16 lg:pb-24">
                        <div className="relative z-10 max-w-2xl">
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d8dfd8] bg-white/70 px-4 py-2 text-xs font-semibold tracking-[0.13em] text-[#41616a] uppercase">
                                <span className="size-2 rounded-full bg-[#b89a5b]" />{' '}
                                Une gestion plus lisible, au quotidien
                            </div>
                            <h1 className="text-4xl leading-[1.08] font-semibold tracking-[-0.04em] text-[#173d48] sm:text-5xl lg:text-6xl">
                                La gestion carcérale,{' '}
                                <span className="text-[#ad8a48]">
                                    claire et coordonnée.
                                </span>
                            </h1>
                            <p className="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">
                                SIGEC est une plateforme dédiée à la gestion des
                                établissements pénitentiaires et au suivi
                                administratif des admissions, des dossiers et
                                des libérations.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                {signedIn ? (
                                    <>
                                        <Link
                                            href="/dashboard"
                                            className="inline-flex items-center rounded-full bg-[#173d48] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#173d48]/15 transition hover:-translate-y-0.5 hover:bg-[#245664]"
                                        >
                                            Ouvrir les établissements{' '}
                                            <ArrowRight
                                                className="ml-2"
                                                size={17}
                                            />
                                        </Link>
                                        <Link
                                            href="/detainees"
                                            className="inline-flex items-center rounded-full border border-[#cbd5d2] bg-white px-6 py-3.5 text-sm font-semibold text-[#173d48] transition hover:bg-[#eef2ee]"
                                        >
                                            Voir les dossiers
                                        </Link>
                                    </>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center rounded-full bg-[#173d48] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#173d48]/15 transition hover:-translate-y-0.5 hover:bg-[#245664]"
                                        >
                                            Accéder à la plateforme{' '}
                                            <ArrowRight
                                                className="ml-2"
                                                size={17}
                                            />
                                        </Link>
                                        <a
                                            href="#missions"
                                            className="inline-flex items-center rounded-full border border-[#cbd5d2] bg-white px-6 py-3.5 text-sm font-semibold text-[#173d48] transition hover:bg-[#eef2ee]"
                                        >
                                            Découvrir SIGEC
                                        </a>
                                    </>
                                )}
                            </div>
                            <p className="mt-5 text-xs text-slate-500">
                                Un outil de travail pour les équipes
                                pénitentiaires et les responsables habilités.
                            </p>
                        </div>

                        <div className="relative mx-auto w-full max-w-xl lg:ml-auto">
                            <div className="absolute -top-10 -right-10 size-48 rounded-full bg-[#d9c18a]/30 blur-3xl" />
                            <div className="absolute -bottom-12 -left-8 size-56 rounded-full bg-[#8da9a0]/25 blur-3xl" />
                            <div className="relative overflow-hidden rounded-[2rem] bg-[#173d48] p-6 text-white shadow-2xl shadow-[#173d48]/20 sm:p-8">
                                <div className="absolute -top-20 -right-16 size-64 rounded-full border border-white/10" />
                                <div className="absolute -top-12 -right-8 size-48 rounded-full border border-white/10" />
                                <div className="relative flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.18em] text-[#d9c18a] uppercase">
                                            SIGEC
                                        </p>
                                        <h2 className="mt-2 text-2xl font-semibold">
                                            Un suivi structuré
                                        </h2>
                                        <p className="mt-2 max-w-sm text-sm leading-6 text-white/65">
                                            Les informations essentielles
                                            réunies dans un espace réservé aux
                                            utilisateurs autorisés.
                                        </p>
                                    </div>
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-[#e2c989]">
                                        <ShieldCheck size={25} />
                                    </span>
                                </div>
                                <div className="relative mt-8 space-y-3">
                                    <div className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.07] p-4">
                                        <span className="flex size-10 items-center justify-center rounded-xl bg-[#d9c18a]/15 text-[#e2c989]">
                                            <Building2 size={19} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold">
                                                Établissements
                                            </span>
                                            <span className="mt-1 block text-xs text-white/55">
                                                Informations et organisation
                                            </span>
                                        </span>
                                        <ArrowRight
                                            className="text-white/40"
                                            size={17}
                                        />
                                    </div>
                                    <div className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.07] p-4">
                                        <span className="flex size-10 items-center justify-center rounded-xl bg-[#d9c18a]/15 text-[#e2c989]">
                                            <ClipboardCheck size={19} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold">
                                                Admissions et libérations
                                            </span>
                                            <span className="mt-1 block text-xs text-white/55">
                                                Événements et décisions
                                                consignés
                                            </span>
                                        </span>
                                        <ArrowRight
                                            className="text-white/40"
                                            size={17}
                                        />
                                    </div>
                                    <div className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.07] p-4">
                                        <span className="flex size-10 items-center justify-center rounded-xl bg-[#d9c18a]/15 text-[#e2c989]">
                                            <LockKeyhole size={19} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold">
                                                Accès encadrés
                                            </span>
                                            <span className="mt-1 block text-xs text-white/55">
                                                Droits associés aux rôles
                                                attribués
                                            </span>
                                        </span>
                                        <ArrowRight
                                            className="text-white/40"
                                            size={17}
                                        />
                                    </div>
                                </div>
                                <div className="relative mt-6 flex items-center gap-3 border-t border-white/10 pt-5 text-xs text-white/55">
                                    <UsersRound
                                        size={16}
                                        className="text-[#d9c18a]"
                                    />{' '}
                                    Conçu pour accompagner le travail des
                                    équipes
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="missions"
                        className="border-y border-[#e1e5df] bg-white/70"
                    >
                        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:px-8 md:grid-cols-3 lg:px-12 lg:py-14">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-[#ad8a48] uppercase">
                                    Une plateforme dédiée
                                </p>
                                <h2 className="mt-3 text-2xl font-semibold tracking-tight text-[#173d48]">
                                    Pour la gestion carcérale
                                </h2>
                            </div>
                            <p className="text-sm leading-7 text-slate-600">
                                Centraliser les repères utiles à la gestion des
                                établissements et des mouvements de détention
                                dans une interface commune.
                            </p>
                            <p className="text-sm leading-7 text-slate-600">
                                Les accès sont réservés aux comptes habilités.
                                Les opérations enregistrées dépendent du rôle
                                attribué et de l’établissement concerné.
                            </p>
                        </div>
                    </section>
                </main>
                <footer className="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-7 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                    <span className="font-semibold tracking-[0.12em] text-[#173d48]">
                        SIGEC · Gestion carcérale
                    </span>
                    <span>
                        Plateforme de gestion des établissements pénitentiaires
                    </span>
                </footer>
            </div>
        </>
    );
}
