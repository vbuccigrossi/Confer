<template>
    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Outer ring with glow effect -->
        <circle cx="24" cy="24" r="22" stroke="url(#gradient)" stroke-width="2" fill="none" opacity="0.6"/>
        
        <!-- C shape for Confer -->
        <path d="M 32 12 A 12 12 0 1 0 32 36" stroke="url(#gradient)" stroke-width="3" stroke-linecap="round" fill="none"/>
        
        <!-- Tech circuit lines -->
        <line x1="14" y1="24" x2="10" y2="24" stroke="url(#gradient)" stroke-width="1.5"/>
        <line x1="10" y1="24" x2="8" y2="20" stroke="url(#gradient)" stroke-width="1.5"/>
        <line x1="10" y1="24" x2="8" y2="28" stroke="url(#gradient)" stroke-width="1.5"/>
        
        <!-- Corner accents -->
        <line x1="38" y1="10" x2="42" y2="6" stroke="url(#gradient)" stroke-width="1.5"/>
        <line x1="38" y1="38" x2="42" y2="42" stroke="url(#gradient)" stroke-width="1.5"/>
        
        <!-- Small tech dots -->
        <circle cx="38" cy="10" r="1.5" fill="url(#gradient)"/>
        <circle cx="38" cy="38" r="1.5" fill="url(#gradient)"/>
        <circle cx="8" cy="20" r="1.5" fill="url(#gradient)"/>
        <circle cx="8" cy="28" r="1.5" fill="url(#gradient)"/>
        
        <!-- Gradient definition -->
        <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" class="text-light-accent dark:text-dark-accent" style="stop-color:currentColor" />
                <stop offset="100%" class="text-light-primary dark:text-dark-primary" style="stop-color:currentColor" />
            </linearGradient>
        </defs>
    </svg>
</template>
