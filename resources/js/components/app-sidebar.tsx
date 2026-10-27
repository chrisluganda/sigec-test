import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Boxes,
    LayoutDashboard,
    Stethoscope,
    Users,
    UsersRound,
    ClipboardList,
    Siren,
    Gavel,
    ScrollText,
    ClipboardCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const isCreatingEstablishment = page.url.startsWith(
        '/establishments/create',
    );
    const authUser = (
        page.props as { auth?: { user?: { is_central_admin?: boolean } } }
    ).auth?.user;
    const canManageStocks = Boolean(
        (page.props as { canManageStocks?: boolean }).canManageStocks,
    );
    const canManageClinic = Boolean(
        (page.props as { canManageClinic?: boolean }).canManageClinic,
    );
    const canManageVisits = Boolean(
        (page.props as { canManageVisits?: boolean }).canManageVisits,
    );
    const canManageIncidents = Boolean(
        (page.props as { canManageIncidents?: boolean }).canManageIncidents,
    );
    const mainNavItems: NavItem[] = [
        { title: 'Tableau de bord', href: '/dashboard', icon: LayoutDashboard },
        { title: 'Établissements', href: '/establishments', icon: Building2 },
        ...(!isCreatingEstablishment
            ? [
                  {
                      title: 'Personnes détenues',
                      href: '/detainees',
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(!isCreatingEstablishment &&
        (authUser?.is_central_admin ||
            Boolean(
                (page.props as { canReviewAdmissions?: boolean })
                    .canReviewAdmissions,
            ))
            ? [
                  {
                      title: 'Pré-admissions',
                      href: '/admission-reviews',
                      icon: ClipboardCheck,
                  },
              ]
            : []),
        ...(canManageStocks
            ? [{ title: 'Stocks', href: '/stocks', icon: Boxes }]
            : []),
        ...(canManageClinic
            ? [{ title: 'Clinique', href: '/clinic', icon: Stethoscope }]
            : []),
        ...(canManageVisits
            ? [{ title: 'Visites', href: '/visits', icon: ClipboardList }]
            : []),
        ...(canManageIncidents
            ? [{ title: 'Incidents', href: '/incidents', icon: Siren }]
            : []),
        ...(authUser?.is_central_admin
            ? [{ title: 'Magistrats', href: '/magistrates', icon: Gavel }]
            : []),
        ...(authUser?.is_central_admin
            ? [
                  {
                      title: 'Journal d’audit',
                      href: '/audit-log',
                      icon: ScrollText,
                  },
              ]
            : []),
        ...(!isCreatingEstablishment && authUser?.is_central_admin
            ? [{ title: 'Utilisateurs', href: '/users', icon: Users }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
