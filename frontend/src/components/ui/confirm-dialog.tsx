import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";

type ConfirmDialogProps = {
 open:boolean;
 onOpenChange:(open:boolean)=>void;
 title:string;
 description:string;
 confirmText?:string;
 destructive?:boolean;
 onConfirm:()=>void|Promise<void>;
};
export function ConfirmDialog({open,onOpenChange,title,description,confirmText="确认",destructive=false,onConfirm}:ConfirmDialogProps){
 return <Dialog open={open} onOpenChange={onOpenChange}><DialogContent><DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{description}</DialogDescription></DialogHeader><DialogFooter><Button type="button" variant="outline" onClick={()=>onOpenChange(false)}>取消</Button><Button type="button" variant={destructive?"destructive":"default"} onClick={()=>{onOpenChange(false);void onConfirm();}}>{confirmText}</Button></DialogFooter></DialogContent></Dialog>;
}
