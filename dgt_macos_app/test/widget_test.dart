import 'package:flutter_test/flutter_test.dart';

import 'package:dgt_macos_app/main.dart';

void main() {
  test('KIUQ app has a configured website URL', () {
    expect(appBaseUrl, isNotEmpty);
    expect(Uri.parse(appBaseUrl).hasScheme, isTrue);
  });
}
