import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Establishment = {
    id: number; code: string; name: string; can_manage_structure: boolean; province: string | null; city: string | null; status: string;
    facility_type: string | null; administration_type: string | null; security_level: string | null; penitentiary_region: string | null;
};
type Props = { establishments: Establishment[]; canCreate: boolean; canViewDetainees: boolean; canManageUsers: boolean };
const typeLabels: Record<string, string> = {
    arrest_correction: 'Maison d’arrêt et de correction', central_prison: 'Prison centrale',
    detention_center: 'Centre de détention', high_security_prison: 'Prison de haute sécurité', military_prison: 'Prison militaire',
};
const securityLabels: Record<string, string> = { minimum: 'Minimale', medium: 'Moyenne', maximum: 'Maximale' };

export default function EstablishmentsIndex({ establishments, canCreate, canViewDetainees, canManageUsers }: Props) {
    return (
        <>
            <Head title="Établissements" />
            <div className="flex flex-col space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading variant="small" title="Établissements" description="Liste des établissements enregistrés dans SIGEC." />
                    <div className="flex flex-wrap gap-2">{canCreate ? <Button asChild><Link href="/establishments/create">Ajouter un établissement</Link></Button> : null}</div>
                </div>
                {establishments.length === 0 ? (
                    <p className="rounded-lg border p-6 text-center text-muted-foreground">Aucun établissement n’est enregistré pour le moment.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border"><table className="w-full text-left text-sm">
                        <thead className="bg-muted/50"><tr>
                            <th className="px-4 py-3 font-medium">Code</th><th className="px-4 py-3 font-medium">Nom</th>
                            <th className="px-4 py-3 font-medium">Type</th><th className="px-4 py-3 font-medium">Région pénitentiaire</th>
                            <th className="px-4 py-3 font-medium">Sécurité</th><th className="px-4 py-3 font-medium">Statut</th><th className="px-4 py-3 font-medium">Action</th>
                        </tr></thead><tbody>
                            {establishments.map((establishment) => (
                                <tr key={establishment.id} className="border-t">
                                    <td className="px-4 py-3">{establishment.code}</td><td className="px-4 py-3 font-medium">{establishment.name}</td>
                                    <td className="px-4 py-3">{establishment.facility_type ? typeLabels[establishment.facility_type] : 'À classifier'}</td>
                                    <td className="px-4 py-3">{establishment.penitentiary_region ?? '—'}</td>
                                    <td className="px-4 py-3">{establishment.security_level ? securityLabels[establishment.security_level] : '—'}</td>
                                    <td className="px-4 py-3">{establishment.status === 'active' ? 'Actif' : 'Inactif'}</td>
                                    <td className="px-4 py-3"><div className="flex gap-2">{establishment.can_manage_structure ? <Button variant="outline" size="sm" asChild><Link href={`/establishments/${establishment.id}/structure`}>Bâtiments et cellules</Link></Button> : null}<Button variant="outline" size="sm" asChild><Link href={`/establishments/${establishment.id}/edit`}>Modifier</Link></Button></div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table></div>
                )}
            </div>
        </>
    );
}
EstablishmentsIndex.layout = { breadcrumbs: [{ title: 'Établissements', href: '/establishments' }] };




