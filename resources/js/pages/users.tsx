import { Form, Head, router, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/users';

const REGION_LABELS: Record<string, string> = {
    carib: 'Carib',
    networks: 'Networks (+ LATAM)',
};

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    viewer: 'Viewer',
};

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    region: string;
    role: 'admin' | 'viewer';
}

interface UsersProps {
    users: ManagedUser[];
    regions: string[];
    roles: string[];
}

export default function Users({ users, regions, roles }: UsersProps) {
    const { auth } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);

    const updateUser = (
        user: ManagedUser,
        field: 'region' | 'role',
        value: string,
    ) => {
        router.patch(
            UserController.update.url(user.id),
            { [field]: value },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Add users and manage the region and role assigned to each one"
                    />

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button data-test="add-user-button">
                                <UserPlus />
                                Add user
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Add user</DialogTitle>
                                <DialogDescription>
                                    Viewers only see campaigns from their
                                    region. Admins see every region and can
                                    manage users.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                {...UserController.store.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setCreateOpen(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                placeholder="Full name"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                Email address
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                placeholder="Email address"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                name="password"
                                                required
                                                placeholder="Password"
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label>Region</Label>
                                                <Select
                                                    name="region"
                                                    defaultValue="carib"
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {regions.map(
                                                            (region) => (
                                                                <SelectItem
                                                                    key={region}
                                                                    value={
                                                                        region
                                                                    }
                                                                >
                                                                    {REGION_LABELS[
                                                                        region
                                                                    ] ?? region}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.region}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label>Role</Label>
                                                <Select
                                                    name="role"
                                                    defaultValue="viewer"
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {roles.map((role) => (
                                                            <SelectItem
                                                                key={role}
                                                                value={role}
                                                            >
                                                                {ROLE_LABELS[
                                                                    role
                                                                ] ?? role}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.role}
                                                />
                                            </div>
                                        </div>

                                        <div className="flex justify-end">
                                            <Button
                                                disabled={processing}
                                                data-test="create-user-button"
                                            >
                                                Create user
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">
                                    Region
                                </th>
                                <th className="px-4 py-3 font-medium">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-b-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {user.name}
                                        {user.id === auth.user.id && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                You
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Select
                                            value={user.region}
                                            onValueChange={(value) =>
                                                updateUser(
                                                    user,
                                                    'region',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-44">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {regions.map((region) => (
                                                    <SelectItem
                                                        key={region}
                                                        value={region}
                                                    >
                                                        {REGION_LABELS[
                                                            region
                                                        ] ?? region}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Select
                                            value={user.role}
                                            disabled={user.id === auth.user.id}
                                            onValueChange={(value) =>
                                                updateUser(user, 'role', value)
                                            }
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((role) => (
                                                    <SelectItem
                                                        key={role}
                                                        value={role}
                                                    >
                                                        {ROLE_LABELS[role] ??
                                                            role}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
    ],
};
