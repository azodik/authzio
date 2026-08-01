export type DemoMode = 'allow' | 'soft' | 'deny';

export type DemoPolicy = {
    active: boolean;
    capabilities: Record<string, DemoMode>;
    message: string | null;
};

export const emptyDemoPolicy: DemoPolicy = {
    active: false,
    capabilities: {},
    message: null,
};

export function demoMode(policy: DemoPolicy, capability: string): DemoMode {
    if (!policy.active) {
        return 'allow';
    }

    return policy.capabilities[capability] ?? 'deny';
}

export function demoCan(policy: DemoPolicy, capability: string): boolean {
    const mode = demoMode(policy, capability);
    return mode === 'allow' || mode === 'soft';
}

export function demoIsSoft(policy: DemoPolicy, capability: string): boolean {
    return demoMode(policy, capability) === 'soft';
}

export function demoIsDenied(policy: DemoPolicy, capability: string): boolean {
    return demoMode(policy, capability) === 'deny';
}
