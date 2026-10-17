import logo from '../../favicon.svg';

export default function Brand({ compact = false }) {
  return <a className="brand" href="#/login" aria-label="Hema home"><img src={logo} alt="" width="40" height="40"/><span>hema<span className="brand-dot">.</span>{!compact && <small>THE BLOOD BANK NETWORK</small>}</span></a>;
}
