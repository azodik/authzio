import type { Organization } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

export function useActiveOrganization(): Organization | null {
    return useWorkspace().organization;
}
