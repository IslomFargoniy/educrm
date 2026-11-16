import type { SVGAttributes } from 'react';

/**
 * Mensa mark: book / shield with the orange diamond (white parts use currentColor).
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <polygon points="8,16 28.5,23 28.5,58.5 8,47" fill="currentColor" />
            <polygon points="56,16 35.5,23 35.5,58.5 56,47" fill="currentColor" />
            <polygon points="30.5,24 33.5,24 33.5,59.5 32,61 30.5,59.5" fill="currentColor" />
            <polygon points="32,5 41.5,11.5 32,18 22.5,11.5" fill="#F7941D" />
        </svg>
    );
}
