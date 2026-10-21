import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useEffect, useState } from 'react';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const [online, setOnline] = useState(true);
    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {!online ? <div role="status" className="border-b bg-amber-100 px-4 py-2 text-sm text-amber-950">Connexion Internet indisponible. L’interface et ses fichiers statiques restent accessibles ; les dossiers et opérations nécessitent une connexion.</div> : null}
            {children}
        </AppLayoutTemplate>
    );
}
