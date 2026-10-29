import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/brand/sigec-icone.svg"
            alt="Logo SIGEC"
            {...props}
        />
    );
}
