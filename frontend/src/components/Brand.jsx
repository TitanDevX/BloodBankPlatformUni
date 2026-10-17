import logo from '../../favicon.svg';

export default function Brand() {
  return <a className="brand" href="#/login" aria-label="Syrian Blood Network home"><img src={logo} alt="" width="40" height="40"/><span>SBN<small>SYRIAN BLOOD NETWORK</small></span></a>;
}
