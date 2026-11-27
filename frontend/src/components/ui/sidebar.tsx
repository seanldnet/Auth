import * as React from "react";
import { PanelLeft } from "lucide-react";
import { Slot } from "@radix-ui/react-slot";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog";

type SidebarContextValue = { open:boolean; setOpen:(value:boolean)=>void; mobileOpen:boolean; setMobileOpen:(value:boolean)=>void; isMobile:boolean; toggle:()=>void };
const SidebarContext = React.createContext<SidebarContextValue|null>(null);
function useSidebar(){const value=React.useContext(SidebarContext);if(!value)throw new Error("Sidebar components must be used inside SidebarProvider.");return value;}
function SidebarProvider({children,className,...props}:React.HTMLAttributes<HTMLDivElement>){
 const [open,setOpen]=React.useState(true);const [mobileOpen,setMobileOpen]=React.useState(false);const [isMobile,setIsMobile]=React.useState(false);
 React.useEffect(()=>{const media=window.matchMedia("(max-width: 767px)");const update=()=>setIsMobile(media.matches);update();media.addEventListener("change",update);return()=>media.removeEventListener("change",update);},[]);
 const value=React.useMemo(()=>({open,setOpen,mobileOpen,setMobileOpen,isMobile,toggle:()=>isMobile?setMobileOpen(v=>!v):setOpen(v=>!v)}),[open,mobileOpen,isMobile]);
 return <SidebarContext.Provider value={value}><div data-slot="sidebar-wrapper" className={cn("min-h-svh w-full",className)} {...props}>{children}</div></SidebarContext.Provider>;
}
function Sidebar({children,className,...props}:React.HTMLAttributes<HTMLElement>){
 const {open,mobileOpen,setMobileOpen,isMobile}=useSidebar();
 const content=<div data-sidebar="sidebar" className={cn("flex h-full flex-col bg-sidebar text-sidebar-foreground",className)} {...props}>{children}</div>;
 if(isMobile)return <Dialog open={mobileOpen} onOpenChange={setMobileOpen}><DialogContent className="left-0 top-0 h-svh w-[min(86vw,18rem)] max-w-none translate-x-0 translate-y-0 rounded-none border-r p-0"><DialogTitle className="sr-only">导航菜单</DialogTitle><DialogDescription className="sr-only">Auth 管理控制台导航</DialogDescription>{content}</DialogContent></Dialog>;
 return <aside data-slot="sidebar" data-state={open?"expanded":"collapsed"} className={cn("fixed inset-y-0 left-0 z-30 hidden border-r bg-sidebar transition-[width] duration-200 md:flex",open?"w-64":"w-[4.25rem]")}><div className="flex h-full w-full flex-col overflow-hidden">{content}</div></aside>;
}
function SidebarInset({children,className,...props}:React.HTMLAttributes<HTMLElement>){const {open,isMobile}=useSidebar();return <main data-slot="sidebar-inset" className={cn("min-h-svh min-w-0 bg-background transition-[padding] duration-200",!isMobile&&(open?"md:pl-64":"md:pl-[4.25rem]"),className)} {...props}>{children}</main>;}
function SidebarTrigger({className,...props}:React.ComponentProps<typeof Button>){const {toggle}=useSidebar();return <Button type="button" variant="ghost" size="icon" aria-label="切换导航菜单" onClick={toggle} className={cn("shrink-0",className)} {...props}><PanelLeft className="size-5"/></Button>;}
function SidebarHeader({className,...props}:React.HTMLAttributes<HTMLDivElement>){return <div data-slot="sidebar-header" className={cn("flex min-h-16 items-center gap-3 border-b px-4 py-3",className)} {...props}/>;}
function SidebarContent({className,...props}:React.HTMLAttributes<HTMLDivElement>){return <div data-slot="sidebar-content" className={cn("min-h-0 flex-1 space-y-4 overflow-y-auto p-3",className)} {...props}/>;}
function SidebarGroup({className,...props}:React.HTMLAttributes<HTMLDivElement>){return <section data-slot="sidebar-group" className={cn("space-y-1",className)} {...props}/>;}
function SidebarGroupLabel({className,...props}:React.HTMLAttributes<HTMLParagraphElement>){return <p data-slot="sidebar-group-label" className={cn("px-3 py-2 text-xs font-medium text-sidebar-foreground/60",className)} {...props}/>;}
function SidebarMenu({className,...props}:React.HTMLAttributes<HTMLUListElement>){return <ul data-slot="sidebar-menu" className={cn("flex flex-col gap-1",className)} {...props}/>;}
function SidebarMenuItem({className,...props}:React.LiHTMLAttributes<HTMLLIElement>){return <li data-slot="sidebar-menu-item" className={className} {...props}/>;}
function SidebarMenuButton({asChild,isActive,className,children,...props}:React.ButtonHTMLAttributes<HTMLButtonElement>&{asChild?:boolean;isActive?:boolean}){const {open,isMobile}=useSidebar();const Comp=asChild?Slot:"button";return <Comp data-slot="sidebar-menu-button" data-active={isActive||undefined} title={!open&&!isMobile&&typeof children==="string"?children:undefined} className={cn("flex h-9 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-medium text-sidebar-foreground/75 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground",!open&&!isMobile&&"justify-center px-2 [&>span:last-child]:hidden",className)} {...props}>{children}</Comp>;}
function SidebarFooter({className,...props}:React.HTMLAttributes<HTMLDivElement>){return <div data-slot="sidebar-footer" className={cn("border-t p-3 text-xs text-sidebar-foreground/60",className)} {...props}/>;}
export {SidebarProvider,Sidebar,SidebarInset,SidebarTrigger,SidebarHeader,SidebarContent,SidebarGroup,SidebarGroupLabel,SidebarMenu,SidebarMenuItem,SidebarMenuButton,SidebarFooter};
