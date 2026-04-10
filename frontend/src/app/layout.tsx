import type { Metadata } from "next";
import { Geist, Geist_Mono, Inter } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Watch CRM",
  description: "CRM para relojoaria com catálogo, pedidos, envios e autenticação segura.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html:
              '(function(){try{var stored=localStorage.getItem("crm-theme");var theme=stored==="light"||stored==="dark"||stored==="system"?stored:"system";var media=window.matchMedia("(prefers-color-scheme: dark)");var resolved=theme==="system"?(media.matches?"dark":"light"):theme;document.documentElement.dataset.theme=resolved;}catch(e){}})();',
          }}
        />
      </head>
      <body className={`${geistSans.variable} ${geistMono.variable} ${inter.variable}`}>
        {children}
      </body>
    </html>
  );
}
