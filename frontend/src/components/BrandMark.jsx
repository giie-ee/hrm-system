function BrandMark({ compact = false, inverse = false }) {
  return (
    <div className={`brand-lockup${inverse ? ' brand-lockup--inverse' : ''}`}>
      <span className="brand-symbol" aria-hidden="true">
        <span className="brand-symbol__tile brand-symbol__tile--blue" />
        <span className="brand-symbol__tile brand-symbol__tile--green" />
        <span className="brand-symbol__tile brand-symbol__tile--pink" />
      </span>
      {!compact && (
        <span className="brand-copy">
          <strong>Nexa People</strong>
          <small>Clear people operations.</small>
        </span>
      )}
    </div>
  )
}

export default BrandMark
