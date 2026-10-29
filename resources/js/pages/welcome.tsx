import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
} from 'lucide-react';
import { login, register } from '@/routes';

export default function Welcome() {
    const { auth, canRegister } = usePage().props;
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
                        <img
                            src="/images/brand/sigec-logo.svg"
                            alt="SIGEC — Gestion carcérale"
                            className="h-12 w-auto object-contain"
                        />
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
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="hidden rounded-full bg-[#173d48] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#245664] sm:inline-flex"
                                    >
                                        Créer un compte
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto max-w-7xl px-5 pt-12 pb-16 text-center sm:px-8 sm:pt-20 lg:px-12 lg:pt-24 lg:pb-24">
                        <div className="relative z-10 mx-auto max-w-4xl">
                            <h1 className="text-4xl leading-[1.08] font-semibold tracking-[-0.04em] text-[#173d48] sm:text-5xl lg:text-6xl">
                                La gestion carcérale,{' '}
                                <span className="text-[#ad8a48]">
                                    claire et coordonnée.
                                </span>
                            </h1>
                            <p className="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                                SIGEC est une plateforme dédiée à la gestion des
                                établissements pénitentiaires et au suivi
                                administratif des admissions, des dossiers et
                                des libérations.
                            </p>
                            <div className="mt-8 flex flex-wrap justify-center gap-3">
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
