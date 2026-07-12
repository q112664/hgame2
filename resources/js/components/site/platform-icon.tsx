import {
    RiAndroidFill,
    RiAppleFill,
    RiComputerLine,
    RiWindowsFill,
} from '@remixicon/react';
import type { ComponentProps, ComponentType } from 'react';

type IconProps = ComponentProps<typeof RiComputerLine>;

type PlatformIconProps = IconProps & {
    slug: string;
};

const platformIcons: Record<string, ComponentType<IconProps>> = {
    windows: RiWindowsFill,
    ios: RiAppleFill,
    android: RiAndroidFill,
};

export function PlatformIcon({ slug, ...props }: PlatformIconProps) {
    const Icon = platformIcons[slug.toLowerCase()] ?? RiComputerLine;

    return <Icon {...props} />;
}
