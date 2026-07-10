import {
    RiAndroidLine,
    RiAppleLine,
    RiComputerLine,
    RiUbuntuLine,
    RiWindowsFill,
} from '@remixicon/react';
import type { ComponentProps } from 'react';

type PlatformIconProps = ComponentProps<typeof RiComputerLine> & {
    platform: string;
};

export function PlatformIcon({ platform, ...props }: PlatformIconProps) {
    switch (platform) {
        case 'Windows':
            return <RiWindowsFill {...props} />;
        case 'Android':
            return <RiAndroidLine {...props} />;
        case 'macOS':
            return <RiAppleLine {...props} />;
        case 'Linux':
            return <RiUbuntuLine {...props} />;
        default:
            return <RiComputerLine {...props} />;
    }
}
