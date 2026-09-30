export default function RatingStars({ value = 0, reviews = null, size = 'md' }) {
  const rounded = Math.round((value || 0) * 2) / 2;
  const stars = [1, 2, 3, 4, 5].map((star) => {
    if (star <= rounded) return 'full';
    if (star - 0.5 === rounded) return 'half';
    return 'empty';
  });

  return (
    <span className={`rating rating-${size}`}>
      <span className="stars" aria-label={`Valoración ${value} de 5`}>
        {stars.map((kind, i) => (
          <span key={i} className={`star star-${kind}`}>
            ★
          </span>
        ))}
      </span>
      <span className="rating-value">{value ? value.toFixed(1) : '—'}</span>
      {reviews != null && <span className="rating-reviews">({reviews})</span>}
    </span>
  );
}
