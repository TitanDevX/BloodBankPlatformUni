import { Building2, Check, Heart, Hospital, Droplet } from 'lucide-react';
export default function NetworkIllustration() {
  return <div className="network-art" role="img" aria-label="A donor connects to a blood bank, where screened components can fulfill a hospital request.">
    <div className="orbit orbit-one"/><div className="orbit orbit-two"/>
    <svg className="network-lines" viewBox="0 0 560 310" fill="none" aria-hidden="true"><path d="M110 186C170 186 163 100 265 100S387 170 450 170" stroke="#c7b5b3" strokeWidth="1.5" strokeDasharray="5 6"/><path d="M280 109V244H440" stroke="#c7b5b3" strokeWidth="1.5" strokeDasharray="5 6"/><circle cx="177" cy="141" r="5" fill="#a73549"/><circle cx="375" cy="132" r="5" fill="#a73549"/></svg>
    <div className="network-card donor-node"><span className="node-icon rose"><Heart size={22}/></span><div><strong>A generous donor</strong><small>Where it all begins</small></div><span className="tiny-check"><Check size={11}/></span></div>
    <div className="bank-node"><span className="bank-symbol"><Building2 size={36} strokeWidth={1.4}/><span><Droplet size={14} fill="currentColor"/></span></span><strong>Your blood bank</strong><small>Every donation, accounted for</small><div className="screening-pill"><span/> Screened. Tracked. Connected.</div></div>
    <div className="network-card hospital-node"><span className="node-icon sage"><Hospital size={23}/></span><div><strong>A hospital in need</strong><small>The next step in care</small></div></div>
    <div className="component-note"><span className="note-dot"/><span>One connected journey</span><Heart size={12}/></div>
    <span className="art-spark spark-one">+</span><span className="art-spark spark-two">+</span>
  </div>;
}
