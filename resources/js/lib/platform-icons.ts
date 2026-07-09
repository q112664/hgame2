import {
    RiAndroidLine,
    RiAppleLine,
    RiComputerLine,
    RiUbuntuLine,
    RiWindowsFill,
    type RemixiconComponentType,
} from '@remixicon/react';

const platformIcons: Record<string, RemixiconComponentType> = {
    Windows: RiWindowsFill,
    Android: RiAndroidLine,
    macOS: RiAppleLine,
    Linux: RiUbuntuLine,
};

export function getPlatformIcon(platform: string): RemixiconComponentType {
    return platformIcons[platform] ?? RiComputerLine;
}
