import { useCallback, useEffect, useState } from "react";
import { ArrowDownLeft, ArrowUpRight, LoaderCircle, RefreshCw, Wallet } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
type Tx={id:number;direction:"CREDIT"|"DEBIT"|"REVERSAL";amount:string;balance_before:string;balance_after:string;business_type:string;description:string;created_at:string};
type Data={balance:string;updated_at:string|null;display_unit:"CNY"|"U";transactions:Tx[]};
async function api<T>(url:string):Promise<T>{const r=await fetch(url,{credentials:"include",headers:{Accept:"application/json"}});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||"请求失败");return j.data as T;}
export function WalletPage(){const [data,setData]=useState<Data|null>(null);const [loading,setLoading]=useState(true);const [error,setError]=useState("");
 const load=useCallback(async()=>{setLoading(true);setError("");try{setData(await api<Data>("/api/wallet"));}catch(e){setError(e instanceof Error?e.message:"读取钱包失败");}finally{setLoading(false);}},[]);
 useEffect(()=>{void load();},[load]);
 const format=(v:string)=>data?.display_unit==="CNY"?"¥"+v:v+" U";
 const direction:Record<string,string>={CREDIT:"入账",DEBIT:"扣款",REVERSAL:"退回"};
 return <section className="flex flex-col gap-6"><div className="flex flex-wrap items-start justify-between gap-4"><div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">财务中心</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">我的钱包</h1><p className="text-sm text-muted-foreground">每个账号只有一个数值余额；页面单位由管理员统一设置，只改变显示，不进行币种换算。</p></div><Button variant="outline" onClick={()=>void load()} disabled={loading}><RefreshCw className="size-4"/>刷新</Button></div>
 {error?<div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div>:null}
 <Card><CardHeader><CardDescription>当前可用余额</CardDescription><CardTitle className="flex items-center gap-3 text-4xl tabular-nums">{loading?<LoaderCircle className="size-7 animate-spin"/>:data?format(data.balance):"—"}<Wallet className="size-7 text-muted-foreground"/></CardTitle><CardDescription>最近更新：{data?.updated_at||"—"}</CardDescription></CardHeader><CardContent><p className="text-xs text-muted-foreground">显示单位：{data?.display_unit==="U"?"U":"人民币（¥）"}。管理员切换显示单位时，余额数值不会改变。</p></CardContent></Card>
 <Card><CardHeader><CardTitle className="text-base">余额变动流水</CardTitle><CardDescription>最近 50 条入账、扣款和退回记录。</CardDescription></CardHeader><CardContent>{loading?<LoaderCircle className="size-5 animate-spin"/>:!data||data.transactions.length===0?<p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">暂无余额流水。</p>:<div className="overflow-x-auto"><Table className="min-w-[760px]"><TableHeader><TableRow><TableHead>时间</TableHead><TableHead>类型</TableHead><TableHead>金额</TableHead><TableHead>变动前</TableHead><TableHead>变动后</TableHead><TableHead>说明</TableHead></TableRow></TableHeader><TableBody>{data.transactions.map(x=><TableRow key={x.id}><TableCell>{x.created_at}</TableCell><TableCell><Badge variant={x.direction==="CREDIT"?"success":"secondary"}>{direction[x.direction]||x.direction}</Badge></TableCell><TableCell><span className="inline-flex items-center gap-1">{x.direction==="DEBIT"?<ArrowUpRight className="size-3 text-destructive"/>:<ArrowDownLeft className="size-3 text-emerald-600"/>}{format(x.amount)}</span></TableCell><TableCell className="tabular-nums">{format(x.balance_before)}</TableCell><TableCell className="tabular-nums">{format(x.balance_after)}</TableCell><TableCell>{x.description}</TableCell></TableRow>)}</TableBody></Table></div>}</CardContent></Card>
 </section>;
}
