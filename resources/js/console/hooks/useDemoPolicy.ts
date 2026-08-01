import { useAuth } from '@/auth/AuthContext';
import {
    type DemoMode,
    type DemoPolicy,
    demoCan,
    demoIsDenied,
    demoIsSoft,
    demoMode,
} from '@/lib/demoPolicy';

export function useDemoPolicy(): {
    policy: DemoPolicy;
    active: boolean;
    mode: (capability: string) => DemoMode;
    can: (capability: string) => boolean;
    isSoft: (capability: string) => boolean;
    isDenied: (capability: string) => boolean;
} {
    const { demo } = useAuth();

    return {
        policy: demo,
        active: demo.active,
        mode: (capability) => demoMode(demo, capability),
        can: (capability) => demoCan(demo, capability),
        isSoft: (capability) => demoIsSoft(demo, capability),
        isDenied: (capability) => demoIsDenied(demo, capability),
    };
}
